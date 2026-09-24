<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\LegalController.
 */

/**
 * The three documents, each named by the route that serves it, the
 * Inertia page that renders it and the Markdown file it is written in.
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function legalDocuments(): array
{
    return [
        'privacy' => ['legal.privacy', 'marketing/legal/privacy', 'privacy'],
        'terms' => ['legal.terms', 'marketing/legal/terms', 'terms'],
        'refunds' => ['legal.refunds', 'marketing/legal/refunds', 'refunds'],
    ];
}

describe('documents', function (): void {
    it('renders the document for a guest', function (string $route, string $component): void {
        get(route($route, ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component($component)
                ->where('user', null)
                ->where('locale', 'en')
                ->where('dashboardUrl', null));
    })->with(legalDocuments());

    it('renders the document in spanish', function (string $route, string $component): void {
        get(route($route, ['locale' => 'es']))
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component($component)
                ->where('locale', 'es'));
    })->with(legalDocuments());

    it('404s for an unsupported locale', function (string $route): void {
        get(str_replace('/en/', '/fr/', route($route, ['locale' => 'en'])))
            ->assertNotFound();
    })->with(legalDocuments());

    it('points its alternates at both locales of the same document', function (string $route): void {
        get(route($route, ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('alternates.en', route($route, ['locale' => 'en']))
                ->where('alternates.es', route($route, ['locale' => 'es']))
                ->where('alternates.default', route($route, ['locale' => 'en'])));
    })->with(legalDocuments());

    it('leads back to the homepage of the locale being read', function (string $route): void {
        get(route($route, ['locale' => 'es']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('homeUrl', route('home', ['locale' => 'es'])));
    })->with(legalDocuments());

    it('leaves the entity and the law to the copy itself', function (string $route): void {
        get(route($route, ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->missing('legal'));
    })->with(legalDocuments());
});

describe('markdown', function (): void {
    it('renders the copy and the date of its markdown file', function (string $route, string $component, string $name, string $locale): void {
        $markdown = file_get_contents(resource_path("markdown/legal/{$locale}/{$name}.md")) ?: '';

        // The first section heading and the date as the
        // file spells them.
        preg_match('/^## (.+)$/m', $markdown, $heading);
        preg_match('/^updated: "(.+)"$/m', $markdown, $updated);

        get(route($route, ['locale' => $locale]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('document.updated', $updated[1])
                ->where('document.html', fn (string $html): bool => str_contains($html, $heading[1].'</h2>')
                    && str_contains($html, 'href="mailto:hello@contamos.xyz"')));
    })->with(legalDocuments())->with(['en', 'es']);

    it('serves the document as markdown', function (string $route, string $component, string $name, string $locale): void {
        $response = get(route('legal.markdown', ['locale' => $locale, 'document' => $name]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        expect($response->getContent())
            ->toStartWith('# ')
            ->toContain('## 1. ')
            ->not->toContain('updated:');
    })->with(legalDocuments())->with(['en', 'es']);

    it('404s the markdown of an unsupported locale', function (string $route, string $component, string $name): void {
        get("/fr/{$name}.md")
            ->assertNotFound();
    })->with(legalDocuments());

    it('404s the markdown of a document that does not exist', function (): void {
        get('/en/cookies.md')
            ->assertNotFound();
    });
});

describe('reachability', function (): void {
    it('lists every document in the sitemap', function (string $route): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertSee('<loc>'.route($route, ['locale' => 'en']).'</loc>', false)
            ->assertSee('<loc>'.route($route, ['locale' => 'es']).'</loc>', false);
    })->with(legalDocuments());
});
