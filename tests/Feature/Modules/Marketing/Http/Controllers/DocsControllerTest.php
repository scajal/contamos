<?php

declare(strict_types=1);

use App\Modules\Marketing\Enums\Docs\Page;
use App\Modules\Mcp\Helpers\AssistantGuide;
use App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\DocsController.
 */

/**
 * Every docs page in both locales, so a page whose Markdown is
 * missing in either language fails the suite.
 *
 * @return array<string, array{0: \App\Modules\Marketing\Enums\Docs\Page, 1: string}>
 */
function docsPages(): array
{
    $pages = [];

    foreach (Page::cases() as $page) {
        foreach (['en', 'es'] as $locale) {
            $pages["{$page->value} ({$locale})"] = [$page, $locale];
        }
    }

    return $pages;
}

describe('index', function (): void {
    it('renders the introduction', function (string $locale): void {
        get(route('docs.index', ['locale' => $locale]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('marketing/docs/show')
                ->where('locale', $locale)
                ->where('page', Page::Introduction->value)
                ->where('alternates.en', route('docs.index', ['locale' => 'en']))
                ->where('alternates.es', route('docs.index', ['locale' => 'es'])));
    })->with(['en', 'es']);

    it('404s for an unsupported locale', function (): void {
        get('/fr/docs')
            ->assertNotFound();
    });
});

describe('show', function (): void {
    it('renders the page from its markdown', function (Page $docs, string $locale): void {
        $markdown = file_get_contents(resource_path("markdown/docs/{$locale}/{$docs->value}.md")) ?: '';

        // The title and the Pro flag as the front matter
        // spells them.
        preg_match('/^title: "(.+)"$/m', $markdown, $title);
        $pro = str_contains($markdown, "\npro: true\n");

        get(route('docs.show', ['locale' => $locale, 'page' => $docs->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('marketing/docs/show')
                ->where('page', $docs->value)
                ->where('document.title', $title[1])
                ->where('document.description', fn (string $description): bool => $description !== '')
                ->where('document.pro', $pro)
                ->where('document.html', fn (string $html): bool => $html !== '')
                ->has('document.headings')
                ->where('group', $docs->group()->getLabel())
                ->where('markdownUrl', route('docs.markdown', ['locale' => $locale, 'page' => $docs->value]))
                ->where('alternates.en', $docs->url('en'))
                ->where('alternates.es', $docs->url('es'))
                ->where('alternates.default', $docs->url('en'))
                ->where('navigation', fn (\Illuminate\Support\Collection $navigation): bool => $navigation
                    ->flatMap(fn (array $group): array => $group['pages'])
                    ->pluck('page')
                    ->all() === array_column(Page::cases(), 'value')));
    })->with(docsPages());

    it('anchors every heading of the table of contents', function (): void {
        get(route('docs.show', ['locale' => 'en', 'page' => Page::Introduction->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('document.headings.0.id', 'what-contamos-is')
                ->where('document.headings.0.text', 'What Contamos is')
                ->where('document.html', fn (string $html): bool => str_contains($html, '<h2 id="what-contamos-is">')
                    && str_contains($html, 'href="#what-contamos-is"')));
    });

    it('404s for a page that does not exist', function (): void {
        get('/en/docs/pricing')
            ->assertNotFound();
    });

    it('404s for an unsupported locale', function (): void {
        get('/fr/docs/'.Page::Introduction->value)
            ->assertNotFound();
    });
});

describe('assistants', function (): void {
    it('shows the connection steps and example prompts the app shares', function (string $locale): void {
        get(route('docs.show', ['locale' => $locale, 'page' => Page::AiAssistants->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('assistants.guides', AssistantGuide::guides($locale))
                ->where('assistants.prompts', AssistantGuide::prompts($locale))
                ->where('assistants.server_url', AuthenticateMcpToken::resource()));
    })->with(['en', 'es']);

    it('leaves the guide off every other page', function (): void {
        get(route('docs.show', ['locale' => 'en', 'page' => Page::Quickstart->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('assistants', null));
    });

    it('ends its markdown with the steps and the prompts', function (): void {
        $markdown = get(route('docs.markdown', ['locale' => 'en', 'page' => Page::AiAssistants->value]))
            ->assertOk()
            ->getContent();

        expect($markdown)
            ->toContain('## How to connect')
            ->toContain(AuthenticateMcpToken::resource())
            ->toContain('1. '.AssistantGuide::guides()[0]['steps'][0])
            ->toContain('## Example prompts')
            ->toContain('- How are my budgets doing this month? (Pro)');
    });
});

describe('markdown', function (): void {
    it('serves the page as markdown', function (Page $docs, string $locale): void {
        $response = get(route('docs.markdown', ['locale' => $locale, 'page' => $docs->value]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        expect($response->getContent())
            ->toStartWith('# '.$docs->document($locale)->title."\n")
            ->not->toContain('description:')
            ->not->toContain('](/');
    })->with(docsPages());

    it('404s for a page that does not exist', function (): void {
        get('/en/docs/pricing.md')
            ->assertNotFound();
    });

    it('404s for an unsupported locale', function (): void {
        get('/fr/docs/'.Page::Introduction->value.'.md')
            ->assertNotFound();
    });
});

describe('full', function (): void {
    it('joins every english docs page in sidebar order', function (): void {
        $response = get(route('docs.full'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        // Each page's title, where it starts, in the order
        // of the sidebar.
        $positions = array_map(
            fn (Page $page): int|false => strpos($response->getContent() ?: '', '# '.$page->document('en')->title."\n"),
            Page::cases(),
        );

        expect($positions)
            ->not->toContain(false)
            ->toBe(collect($positions)->sort()->values()->all());
    });

    it('leaves the legal documents out', function (): void {
        get(route('docs.full'))
            ->assertOk()
            ->assertDontSee('# Terms of service', false)
            ->assertDontSee('# Privacy policy', false)
            ->assertDontSee('# Refund policy', false);
    });

    it('is linked with every docs page from llms.txt', function (): void {
        $llms = file_get_contents(public_path('llms.txt')) ?: '';

        expect($llms)->toContain('/llms-full.txt');

        foreach (Page::cases() as $page) {
            expect($llms)->toContain('/en/docs/'.$page->value.'.md');
        }
    });
});
