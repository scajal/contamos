<?php

declare(strict_types=1);

use App\Modules\Marketing\Enums\Docs\Page;

use function Pest\Laravel\get;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\SitemapController.
 */
describe('index', function (): void {
    it('serves the sitemap as xml', function (): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    });

    it('lists every public page in every installed locale', function (string $route): void {
        $response = get(route('sitemap'))->assertOk();

        foreach (['en', 'es'] as $locale) {
            $response->assertSee('<loc>'.route($route, ['locale' => $locale]).'</loc>', false);
        }
    })->with(['home', 'legal.privacy', 'legal.terms', 'legal.refunds']);

    it('lists every docs page in both locales with its alternates', function (Page $page): void {
        $response = get(route('sitemap'))->assertOk();

        foreach (['en', 'es'] as $locale) {
            $response
                ->assertSee('<loc>'.$page->url($locale).'</loc>', false)
                ->assertSee('hreflang="'.$locale.'" href="'.$page->url($locale).'"', false);
        }

        $response->assertSee('hreflang="x-default" href="'.$page->url('en').'"', false);
    })->with(Page::cases());

    it('carries both locales of a page as its alternates', function (): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertSee('hreflang="en" href="'.route('legal.terms', ['locale' => 'en']).'"', false)
            ->assertSee('hreflang="es" href="'.route('legal.terms', ['locale' => 'es']).'"', false);
    });

    it('sends a reader of no language of ours to the locale redirect of the homepage', function (): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertSee('hreflang="x-default" href="'.route('home.redirect').'"', false);
    });

    it('parses as a sitemap', function (): void {
        $document = new DOMDocument;
        $document->loadXML(get(route('sitemap'))->assertOk()->getContent() ?: '');

        expect($document->getElementsByTagName('url'))->toHaveCount(2 * (4 + count(Page::cases())));
    });

    it('needs no file in public to serve it', function (): void {
        expect(file_exists(public_path('sitemap.xml')))->toBeFalse();
    });
});

describe('robots', function (): void {
    it('advertises the sitemap at the application url', function (): void {
        get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('Sitemap: '.route('sitemap'), false);
    });

    it('lets every crawler in', function (): void {
        get(route('robots'))
            ->assertOk()
            ->assertSee('User-agent: *', false);
    });
});
