<?php

declare(strict_types=1);

use App\Modules\Marketing\Enums\Compare\Competitor;
use App\Modules\Marketing\Enums\Docs\Page;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

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

    it('sends no robots header, which search console refuses a sitemap for', function (): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    });

    it('lists every public page in every installed locale', function (string $route): void {
        $response = get(route('sitemap'))->assertOk();

        foreach (['en', 'es'] as $locale) {
            $response->assertSee('<loc>'.route($route, ['locale' => $locale]).'</loc>', false);
        }
    })->with(['home', 'legal.privacy', 'legal.terms', 'legal.refunds', 'blog.index']);

    it('lists every docs page in both locales with its alternates', function (Page $page): void {
        $response = get(route('sitemap'))->assertOk();

        foreach (['en', 'es'] as $locale) {
            $response
                ->assertSee('<loc>'.$page->url($locale).'</loc>', false)
                ->assertSee('hreflang="'.$locale.'" href="'.$page->url($locale).'"', false);
        }

        $response->assertSee('hreflang="x-default" href="'.$page->url('en').'"', false);
    })->with(Page::cases());

    it('lists every comparison in both locales with its alternates', function (Competitor $competitor): void {
        $response = get(route('sitemap'))->assertOk();

        foreach (['en', 'es'] as $locale) {
            $response
                ->assertSee('<loc>'.$competitor->url($locale).'</loc>', false)
                ->assertSee('hreflang="'.$locale.'" href="'.$competitor->url($locale).'"', false);
        }

        $response->assertSee('hreflang="x-default" href="'.$competitor->url('en').'"', false);
    })->with(Competitor::cases());

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
        Storage::fake('blog');

        $document = new DOMDocument;
        $document->loadXML(get(route('sitemap'))->assertOk()->getContent() ?: '');

        expect($document->getElementsByTagName('url'))->toHaveCount(2 * (5 + count(Page::cases()) + count(Competitor::cases())));
    });

    it('lists a published post with the translations it has and when it last changed', function (): void {
        Storage::fake('blog');

        writeBlogPost('en', 'hello', ['updated' => '2026-09-10', 'translations' => ['es' => 'hola']]);
        writeBlogPost('es', 'hola', ['translations' => ['en' => 'hello']]);

        $hello = route('blog.show', ['locale' => 'en', 'slug' => 'hello']);
        $hola = route('blog.show', ['locale' => 'es', 'slug' => 'hola']);

        get(route('sitemap'))
            ->assertOk()
            ->assertSee('<url><loc>'.$hello.'</loc><lastmod>2026-09-10</lastmod>'
                .'<xhtml:link rel="alternate" hreflang="en" href="'.$hello.'"/>'
                .'<xhtml:link rel="alternate" hreflang="es" href="'.$hola.'"/>'
                .'<xhtml:link rel="alternate" hreflang="x-default" href="'.$hello.'"/>'
                .'<changefreq>monthly</changefreq><priority>0.7</priority></url>', false)
            ->assertSee('<url><loc>'.$hola.'</loc><lastmod>2026-09-01</lastmod>', false);
    });

    it('gives an untranslated post no alternate in another language', function (): void {
        Storage::fake('blog');

        writeBlogPost('es', 'hola');

        $hola = route('blog.show', ['locale' => 'es', 'slug' => 'hola']);

        get(route('sitemap'))
            ->assertOk()
            ->assertSee('<url><loc>'.$hola.'</loc><lastmod>2026-09-01</lastmod>'
                .'<xhtml:link rel="alternate" hreflang="es" href="'.$hola.'"/>'
                .'<xhtml:link rel="alternate" hreflang="x-default" href="'.$hola.'"/>'
                .'<changefreq>monthly</changefreq><priority>0.7</priority></url>', false);
    });

    it('leaves out a post that is not out yet', function (array $matter): void {
        Storage::fake('blog');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));

        writeBlogPost('en', 'hidden', $matter);

        get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('blog.show', ['locale' => 'en', 'slug' => 'hidden']), false);
    })->with([
        'a draft' => [['draft' => true]],
        'a post dated in the future' => [['published' => '2026-10-01']],
    ]);

    it('points browsers at the stylesheet that makes it readable', function (): void {
        get(route('sitemap'))
            ->assertOk()
            ->assertSee('<?xml-stylesheet type="text/xsl" href="'.route('sitemap.stylesheet', absolute: false).'"?>', false);
    });

    it('says how often each kind of page changes and how it ranks', function (string $url, string $changefreq, string $priority): void {
        $document = new DOMDocument;
        $document->loadXML(get(route('sitemap'))->assertOk()->getContent() ?: '');

        $page = collect($document->getElementsByTagName('url'))
            ->first(fn (DOMElement $element): bool => $element->getElementsByTagName('loc')->item(0)?->textContent === $url);

        expect($page)->toBeInstanceOf(DOMElement::class)
            ->and($page?->getElementsByTagName('changefreq')->item(0)?->textContent)->toBe($changefreq)
            ->and($page?->getElementsByTagName('priority')->item(0)?->textContent)->toBe($priority);
    })->with([
        'the homepage' => fn (): array => [route('home', ['locale' => 'en']), 'weekly', '1.0'],
        'a legal page' => fn (): array => [route('legal.terms', ['locale' => 'es']), 'yearly', '0.3'],
        'a docs page' => fn (): array => [Page::cases()[0]->url('en'), 'monthly', '0.6'],
        'a comparison' => fn (): array => [Competitor::Splitwise->url('es'), 'monthly', '0.8'],
    ]);

    it('needs no file in public to serve it', function (): void {
        expect(file_exists(public_path('sitemap.xml')))->toBeFalse();
    });
});

describe('stylesheet', function (): void {
    it('serves the sitemap stylesheet as xsl', function (): void {
        $response = get(route('sitemap.stylesheet'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xsl; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex');

        $document = new DOMDocument;

        expect($document->loadXML($response->getContent() ?: ''))->toBeTrue()
            ->and($document->documentElement?->localName)->toBe('stylesheet');
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
