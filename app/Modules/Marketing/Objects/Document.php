<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Objects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Query;
use League\CommonMark\Node\StringContainerHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * A long-form page written as a Markdown file with front
 * matter, such as a legal document or a docs page, read
 * from resources/markdown and rendered to HTML with a
 * table of contents built from its headings.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
class Document implements Arrayable
{
    /**
     * Create a new instance of the class.
     *
     * @param  array<int, array{id: string, text: string, level: int}>  $headings
     * @param  array<string, mixed>  $matter
     */
    public function __construct(
        public string $title,
        public string $description,
        public bool $pro,
        public ?string $updated,
        public string $body,
        public string $html,
        public array $headings,
        public array $matter = [],
    ) {}

    /**
     * Read the document at the given path under
     * resources/markdown, in the given locale, or 404
     * when that locale has no such file.
     */
    public static function find(string $collection, string $name, string $locale): self
    {
        $path = resource_path("markdown/{$collection}/{$locale}/{$name}.md");

        // A page missing in a language is a page that
        // does not exist there.
        abort_unless(File::exists($path), Response::HTTP_NOT_FOUND);

        return self::parse(File::get($path));
    }

    /**
     * Parse a Markdown file with front matter into a
     * document.
     */
    public static function parse(string $markdown): self
    {
        // The file's front matter says what the page is
        // called, and its body is what gets rendered.
        $rendered = self::converter()->convert($markdown);

        /** @var array{title?: string, description?: string, pro?: bool, updated?: string} $matter */
        $matter = $rendered instanceof RenderedContentWithFrontMatter
            ? (array) $rendered->getFrontMatter()
            : [];

        // Every second and third level heading makes an
        // entry of the table of contents, linked by the
        // anchor the permalink extension gave it.
        $headings = [];

        foreach ((new Query)->where(Query::type(Heading::class))->findAll($rendered->getDocument()) as $heading) {
            /** @var \League\CommonMark\Extension\CommonMark\Node\Block\Heading $heading */
            $permalink = $heading->firstChild();

            if (! $permalink instanceof HeadingPermalink || $heading->getLevel() < 2 || $heading->getLevel() > 3) {
                continue;
            }

            $headings[] = [
                'id' => $permalink->getSlug(),
                'text' => StringContainerHelper::getChildText($heading),
                'level' => $heading->getLevel(),
            ];
        }

        return new self(
            title: (string) ($matter['title'] ?? ''),
            description: (string) ($matter['description'] ?? ''),
            pro: (bool) ($matter['pro'] ?? false),
            updated: $matter['updated'] ?? null,
            body: Str::trim(preg_replace('/\A---\R.*?\R---\R/s', '', $markdown) ?? $markdown),
            html: $rendered->getContent(),
            headings: $headings,
            // Every key of the front matter as it was written,
            // for a page such as a blog post that reads more of
            // it than the keys above.
            matter: $matter,
        );
    }

    /**
     * The page as Markdown for a reader that is not a
     * browser: its title as the only first-level heading,
     * followed by the body as it was written, with its
     * links to other pages made absolute so they still
     * lead somewhere once the text is copied elsewhere.
     */
    public function markdown(): string
    {
        $body = Str::replace('](/', ']('.url('/').'/', $this->body);

        return "# {$this->title}\n\n{$body}\n";
    }

    /**
     * Get the instance as an array.
     *
     * @return array{title: string, description: string, pro: bool, updated: string|null, html: string, headings: array<int, array{id: string, text: string, level: int}>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'pro' => $this->pro,
            'updated' => $this->updated,
            'html' => $this->html,
            'headings' => $this->headings,
        ];
    }

    /**
     * The converter every document is rendered with:
     * CommonMark with GitHub's tables and autolinks, front
     * matter, and an anchor on every heading.
     */
    protected static function converter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'before',
                'min_heading_level' => 2,
                'symbol' => '#',
                'title' => '',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension(new FrontMatterParser));
        $environment->addExtension(new HeadingPermalinkExtension);

        return new MarkdownConverter($environment);
    }
}
