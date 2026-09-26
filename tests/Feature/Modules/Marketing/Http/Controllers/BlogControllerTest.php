<?php

declare(strict_types=1);

use App\Modules\Marketing\Objects\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\BlogController.
 */
describe('index', function (): void {
    it('lists the published posts in the reader\'s language, newest first', function (): void {
        Storage::fake('blog');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));

        writeBlogPost('en', 'older', ['published' => '2026-08-01']);
        writeBlogPost('en', 'newer', ['published' => '2026-09-20']);
        writeBlogPost('es', 'en-espanol');

        get(route('blog.index', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('marketing/blog/index')
                ->where('posts.0.slug', 'newer')
                ->where('posts.0.url', route('blog.show', ['locale' => 'en', 'slug' => 'newer']))
                ->where('posts.0.published', '2026-09-20')
                ->where('posts.1.slug', 'older')
                ->has('posts', 2)
                ->where('feedUrl', route('blog.feed', ['locale' => 'en']))
                ->where('alternates.es', route('blog.index', ['locale' => 'es'])));
    });

    it('leaves out drafts and posts dated in the future', function (): void {
        Storage::fake('blog');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));

        writeBlogPost('en', 'out');
        writeBlogPost('en', 'draft', ['draft' => true]);
        writeBlogPost('en', 'scheduled', ['published' => '2026-10-01']);

        get(route('blog.index', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('posts', 1)
                ->where('posts.0.slug', 'out'));
    });

    it('renders with no posts at all', function (): void {
        Storage::fake('blog');

        get(route('blog.index', ['locale' => 'es']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('posts', []));
    });

    it('404s for an unsupported locale', function (): void {
        get('/fr/blog')
            ->assertNotFound();
    });
});

describe('show', function (): void {
    it('renders the post from its markdown', function (): void {
        Storage::fake('blog');

        writeBlogPost('en', 'hello', [
            'title' => 'Hello',
            'description' => 'The first post.',
            'published' => '2026-09-01',
            'updated' => '2026-09-10',
        ], "## A section\n\nSome words.");

        get(route('blog.show', ['locale' => 'en', 'slug' => 'hello']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('marketing/blog/show')
                ->where('post.title', 'Hello')
                ->where('post.description', 'The first post.')
                ->where('post.published', '2026-09-01')
                ->where('post.updated', '2026-09-10')
                ->where('post.minutes', 1)
                ->where('post.draft', false)
                ->where('post.image', url('/images/og.png'))
                ->where('post.headings.0.id', 'a-section')
                ->where('post.html', fn (string $html): bool => str_contains($html, '<p>Some words.</p>'))
                ->where('markdownUrl', route('blog.markdown', ['locale' => 'en', 'slug' => 'hello']))
                ->where('blogUrl', route('blog.index', ['locale' => 'en'])));
    });

    it('links the post to its translation', function (): void {
        Storage::fake('blog');

        writeBlogPost('en', 'hello', ['translations' => ['es' => 'hola']]);
        writeBlogPost('es', 'hola', ['translations' => ['en' => 'hello']]);

        get(route('blog.show', ['locale' => 'es', 'slug' => 'hola']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('versions', [
                    'es' => route('blog.show', ['locale' => 'es', 'slug' => 'hola']),
                    'en' => route('blog.show', ['locale' => 'en', 'slug' => 'hello']),
                ])
                ->where('alternates.en', route('blog.show', ['locale' => 'en', 'slug' => 'hello']))
                ->where('alternates.default', route('blog.show', ['locale' => 'en', 'slug' => 'hello'])));
    });

    it('sends the switcher to the blog of a language the post has no translation in', function (): void {
        Storage::fake('blog');

        // The translation it names was never written.
        writeBlogPost('en', 'hello', ['translations' => ['es' => 'hola']]);

        get(route('blog.show', ['locale' => 'en', 'slug' => 'hello']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('versions', ['en' => route('blog.show', ['locale' => 'en', 'slug' => 'hello'])])
                ->where('alternates.es', route('blog.index', ['locale' => 'es'])));
    });

    it('previews a draft outside production', function (): void {
        Storage::fake('blog');

        writeBlogPost('en', 'draft', ['draft' => true]);

        get(route('blog.show', ['locale' => 'en', 'slug' => 'draft']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('post.draft', true));
    });

    it('404s for a post that is not out yet in production', function (array $matter): void {
        Storage::fake('blog');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        app()->detectEnvironment(fn (): string => 'production');

        writeBlogPost('en', 'hidden', $matter);

        get(route('blog.show', ['locale' => 'en', 'slug' => 'hidden']))
            ->assertNotFound();
    })->with([
        'a draft' => [['draft' => true]],
        'a post dated in the future' => [['published' => '2026-10-01']],
    ]);

    it('404s for a post written only in another language', function (): void {
        Storage::fake('blog');

        writeBlogPost('es', 'hola');

        get('/en/blog/hola')
            ->assertNotFound();
    });

    it('404s for a slug that could leave the blog directory', function (): void {
        Storage::fake('blog');

        get('/en/blog/..%2F..%2Fdocs%2Fen%2Fintroduction')
            ->assertNotFound();
    });
});

describe('markdown', function (): void {
    it('serves the post as markdown', function (): void {
        Storage::fake('blog');

        writeBlogPost('en', 'hello', ['title' => 'Hello'], 'Read [the docs](/en/docs).');

        $response = get(route('blog.markdown', ['locale' => 'en', 'slug' => 'hello']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        expect($response->getContent())->toBe("# Hello\n\nRead [the docs](".url('/en/docs').").\n");
    });

    it('404s for a missing post', function (): void {
        Storage::fake('blog');

        get(route('blog.markdown', ['locale' => 'en', 'slug' => 'missing']))
            ->assertNotFound();
    });
});

describe('feed', function (): void {
    it('lists the published posts as rss', function (): void {
        Storage::fake('blog');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));

        writeBlogPost('en', 'hello', ['title' => 'Hello', 'published' => '2026-09-20']);
        writeBlogPost('en', 'draft', ['draft' => true]);

        $response = get(route('blog.feed', ['locale' => 'en']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $feed = simplexml_load_string($response->getContent() ?: '');

        expect($feed)->not->toBeFalse()
            ->and($feed->channel->item)->toHaveCount(1)
            ->and((string) $feed->channel->item->title)->toBe('Hello')
            ->and((string) $feed->channel->item->link)->toBe(route('blog.show', ['locale' => 'en', 'slug' => 'hello']))
            ->and((string) $feed->channel->item->pubDate)->toBe('Sun, 20 Sep 2026 00:00:00 +0000');
    });

    it('escapes a title that would break the xml', function (): void {
        Storage::fake('blog');

        writeBlogPost('en', 'ampersand', ['title' => 'Pesos & dollars <together>']);

        $feed = simplexml_load_string(get(route('blog.feed', ['locale' => 'en']))->assertOk()->getContent() ?: '');

        expect((string) $feed->channel->item->title)->toBe('Pesos & dollars <together>');
    });
});

describe('content', function (): void {
    it('parses every post written in the repository', function (string $path): void {
        $locale = basename(dirname($path));

        expect(Post::parse($locale, basename($path, '.md'), File::get($path))->document->title)->not->toBe('');
    })->with(fn (): array => collect(glob(dirname(__DIR__, 6).'/resources/markdown/blog/*/*.md') ?: [])
        // The dataset is built before the application boots, so
        // the directory is found from this file rather than with
        // resource_path().
        ->mapWithKeys(fn (string $path): array => [Str::after($path, 'markdown/blog/') => $path])
        ->all());
});
