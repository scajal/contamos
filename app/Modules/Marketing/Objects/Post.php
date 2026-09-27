<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Objects;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A post of the blog, written as a Markdown file with
 * front matter on the blog disk, one directory per
 * locale. A post lives in its own language: it may have
 * a translation under another slug, named in its front
 * matter, or none at all.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
class Post implements Arrayable
{
    /**
     * The words a reader gets through in a minute, which
     * the reading time is worked out from.
     */
    protected const int WORDS_PER_MINUTE = 200;

    /**
     * Create a new instance of the class.
     *
     * @param  array<string, string>  $translations
     */
    public function __construct(
        public string $locale,
        public string $slug,
        public CarbonImmutable $published,
        public ?CarbonImmutable $updated,
        public ?string $image,
        public ?string $author,
        public bool $draft,
        public array $translations,
        public Document $document,
    ) {}

    /**
     * Every published post in the given locale, newest
     * first. Drafts and posts dated in the future are left
     * out everywhere, so the index, the feed and the
     * sitemap only ever list what a reader may open.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function all(string $locale): Collection
    {
        return collect(Storage::disk('blog')->files($locale))
            ->filter(fn (string $path): bool => Str::endsWith($path, '.md'))
            ->map(fn (string $path): self => self::read($locale, basename($path, '.md')))
            ->filter(fn (self $post): bool => $post->isPublished())
            ->sortByDesc(fn (self $post): int => $post->published->getTimestamp())
            ->values();
    }

    /**
     * The post with the given slug in the given locale, or
     * 404 when there is none a reader may open.
     */
    public static function find(string $locale, string $slug): self
    {
        $post = self::lookup($locale, $slug);

        abort_if(! $post instanceof \App\Modules\Marketing\Objects\Post, Response::HTTP_NOT_FOUND);

        return $post;
    }

    /**
     * The post with the given slug in the given locale, or
     * null when there is no such file or the post is not
     * out yet. Outside production an unpublished post can
     * still be opened by its URL, so a draft is previewed
     * on the page it will be read on.
     */
    public static function lookup(string $locale, string $slug): ?self
    {
        // A post missing in a language is a post that does
        // not exist there.
        if (! Storage::disk('blog')->exists("{$locale}/{$slug}.md")) {
            return null;
        }

        $post = self::read($locale, $slug);

        return $post->isPublished() || ! App::isProduction() ? $post : null;
    }

    /**
     * Parse a Markdown file with front matter into the
     * post with the given slug in the given locale.
     */
    public static function parse(string $locale, string $slug, string $markdown): self
    {
        $document = Document::parse($markdown);
        $matter = $document->matter;

        // A post without a date has no place in the index,
        // which is ordered by it.
        if (! is_string($matter['published'] ?? null)) {
            throw new InvalidArgumentException("The blog post [{$locale}/{$slug}] has no published date.");
        }

        return new self(
            locale: $locale,
            slug: $slug,
            published: CarbonImmutable::parse($matter['published']),
            updated: is_string($matter['updated'] ?? null) ? CarbonImmutable::parse($matter['updated']) : null,
            image: is_string($matter['image'] ?? null) ? $matter['image'] : null,
            author: is_string($matter['author'] ?? null) ? $matter['author'] : null,
            draft: (bool) ($matter['draft'] ?? false),
            translations: self::translations($matter['translations'] ?? null),
            document: $document,
        );
    }

    /**
     * The same post in other languages, each named by its
     * locale and the slug it has there, keeping only the
     * entries written as a locale and a slug.
     *
     * @return array<string, string>
     */
    protected static function translations(mixed $matter): array
    {
        $translations = [];

        foreach (is_array($matter) ? $matter : [] as $locale => $slug) {
            if (is_string($locale) && is_string($slug)) {
                $translations[$locale] = $slug;
            }
        }

        return $translations;
    }

    /**
     * Whether a reader may find the post: it is not a
     * draft and its date has come.
     */
    public function isPublished(): bool
    {
        return ! $this->draft && $this->published->lessThanOrEqualTo(CarbonImmutable::now());
    }

    /**
     * The minutes the post takes to read, never less than
     * one.
     */
    public function minutes(): int
    {
        return max(1, (int) ceil(Str::wordCount($this->document->body) / self::WORDS_PER_MINUTE));
    }

    /**
     * The URL of the post.
     */
    public function url(): string
    {
        return route('blog.show', ['locale' => $this->locale, 'slug' => $this->slug]);
    }

    /**
     * The URL of the post's Markdown.
     */
    public function markdownUrl(): string
    {
        return route('blog.markdown', ['locale' => $this->locale, 'slug' => $this->slug]);
    }

    /**
     * The absolute URL of the post's image, or of the
     * site's own Open Graph image when it has none.
     */
    public function imageUrl(): string
    {
        return url($this->image ?? '/images/og.png');
    }

    /**
     * The post in every language it can be read in, itself
     * included, keyed by locale. A translation named in the
     * front matter whose file is missing, or which is not
     * out yet, is left out.
     *
     * @return array<string, string>
     */
    public function versions(): array
    {
        $versions = [$this->locale => $this->url()];

        foreach ($this->translations as $locale => $slug) {
            $translation = self::lookup($locale, $slug);

            if ($translation instanceof self) {
                $versions[$locale] = $translation->url();
            }
        }

        return $versions;
    }

    /**
     * The URL the language switcher leads to in each locale:
     * the post's translation where it has one, and the blog
     * in that language where it has none. The default is the
     * English post, or this one when there is no English.
     *
     * @return array{en: string, es: string, default: string}
     */
    public function alternates(): array
    {
        $versions = $this->versions();

        return [
            'en' => $versions['en'] ?? route('blog.index', ['locale' => 'en']),
            'es' => $versions['es'] ?? route('blog.index', ['locale' => 'es']),
            'default' => $versions['en'] ?? $this->url(),
        ];
    }

    /**
     * The post as Markdown for a reader that is not a
     * browser.
     */
    public function markdown(): string
    {
        return $this->document->markdown();
    }

    /**
     * The post as the blog's index lists it, without its
     * body.
     *
     * @return array{slug: string, title: string, description: string, published: string, minutes: int, url: string}
     */
    public function summary(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->document->title,
            'description' => $this->document->description,
            'published' => $this->published->toDateString(),
            'minutes' => $this->minutes(),
            'url' => $this->url(),
        ];
    }

    /**
     * Get the instance as an array.
     *
     * @return array{slug: string, title: string, description: string, published: string, updated: string|null, minutes: int, url: string, image: string, author: string|null, draft: bool, html: string, headings: array<int, array{id: string, text: string, level: int}>}
     */
    public function toArray(): array
    {
        return [
            ...$this->summary(),
            'updated' => $this->updated?->toDateString(),
            'image' => $this->imageUrl(),
            'author' => $this->author,
            'draft' => ! $this->isPublished(),
            'html' => $this->document->html,
            'headings' => $this->document->headings,
        ];
    }

    /**
     * Read the post with the given slug in the given locale
     * from the blog disk.
     */
    protected static function read(string $locale, string $slug): self
    {
        return self::parse($locale, $slug, (string) Storage::disk('blog')->get("{$locale}/{$slug}.md"));
    }
}
