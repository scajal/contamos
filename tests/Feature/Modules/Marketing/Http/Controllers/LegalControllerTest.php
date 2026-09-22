<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\LegalController.
 */

/**
 * The three documents, each named by the route that serves it and
 * the Inertia page that renders it.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function legalDocuments(): array
{
    return [
        'privacy' => ['legal.privacy', 'marketing/legal/privacy'],
        'terms' => ['legal.terms', 'marketing/legal/terms'],
        'refunds' => ['legal.refunds', 'marketing/legal/refunds'],
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

describe('reachability', function (): void {
    it('lists every document in the sitemap', function (string $route): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertSee('<loc>'.route($route, ['locale' => 'en']).'</loc>', false)
            ->assertSee('<loc>'.route($route, ['locale' => 'es']).'</loc>', false);
    })->with(legalDocuments());
});
