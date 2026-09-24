<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Marketing\Enums\Docs\Page;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use LaravelLang\Locales\Facades\Locales;

class SitemapController
{
    /**
     * The public pages outside the docs, named by the route
     * that serves each one. Adding a public page means adding
     * it here, and the sitemap follows without anybody
     * editing XML. The docs pages are listed from their own
     * navigation.
     *
     * @var array<int, string>
     */
    protected const array PAGES = [
        'home',
        'legal.privacy',
        'legal.terms',
        'legal.refunds',
    ];

    /**
     * Serve the sitemap, listing every public page in every
     * installed locale with the others as its alternates.
     */
    public function index(): Response
    {
        // Every page in every language, which is what a crawler
        // is asked to index.
        $urls = $this->locales()
            ->flatMap(fn (string $locale): array => array_map(
                fn (array $page): string => $this->url($page, $locale),
                $this->pages(),
            ))
            ->implode('');

        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'.
            $urls.
            '</urlset>',
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml'],
        );
    }

    /**
     * Serve robots.txt, which points at the sitemap above with
     * the application's own host rather than a written-down one.
     */
    public function robots(): Response
    {
        return response(
            "User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain'],
        );
    }

    /**
     * The languages the application is installed in.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function locales(): Collection
    {
        // The locales package types its collection loosely, so the
        // codes are gathered by iteration rather than plucked.
        $codes = [];

        foreach (Locales::installed() as $locale) {
            $codes[] = $locale->code;
        }

        return collect($codes);
    }

    /**
     * Every public page, each as the URL it has in a given
     * locale and the URL a reader of no language of ours is
     * sent to: the named pages first, then the docs in the
     * order of their sidebar.
     *
     * @return array<int, array{url: \Closure(string): string, default: string}>
     */
    protected function pages(): array
    {
        $first = (string) $this->locales()->first();

        return [
            ...array_map(fn (string $name): array => [
                'url' => fn (string $locale): string => route($name, ['locale' => $locale]),
                // The homepage has a route of its own that
                // resolves the language; the documents fall
                // back to the first one.
                'default' => $name === 'home' ? route('home.redirect') : route($name, ['locale' => $first]),
            ], self::PAGES),
            ...array_map(fn (Page $page): array => [
                'url' => $page->url(...),
                'default' => $page->url($first),
            ], Page::cases()),
        ];
    }

    /**
     * One page in one locale, carrying every locale of the same
     * page as an alternate so a crawler serves the right
     * language to the right reader.
     *
     * @param  array{url: \Closure(string): string, default: string}  $page
     */
    protected function url(array $page, string $locale): string
    {
        // The same page in each of the other languages.
        $alternates = $this->locales()
            ->map(fn (string $code): string => $this->alternate($code, $page['url']($code)))
            ->implode('');

        return '<url><loc>'.e($page['url']($locale)).'</loc>'
            .$alternates
            .$this->alternate('x-default', $page['default'])
            .'</url>';
    }

    /**
     * One hreflang alternate of a page.
     */
    protected function alternate(string $hreflang, string $url): string
    {
        return '<xhtml:link rel="alternate" hreflang="'.e($hreflang).'" href="'.e($url).'"/>';
    }
}
