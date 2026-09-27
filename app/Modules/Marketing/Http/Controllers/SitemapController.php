<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Marketing\Enums\Docs\Page;
use App\Modules\Marketing\Objects\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use LaravelLang\Locales\Facades\Locales;

class SitemapController
{
    /**
     * The public pages outside the docs, named by the route
     * that serves each one. Adding a public page means adding
     * it here, and the sitemap follows without anybody
     * editing XML. Each says how often it changes and how
     * it ranks against the others. The docs pages are listed
     * from their own navigation.
     *
     * @var array<string, array{changefreq: string, priority: string}>
     */
    protected const array PAGES = [
        'home' => ['changefreq' => 'weekly', 'priority' => '1.0'],
        'legal.privacy' => ['changefreq' => 'yearly', 'priority' => '0.3'],
        'legal.terms' => ['changefreq' => 'yearly', 'priority' => '0.3'],
        'legal.refunds' => ['changefreq' => 'yearly', 'priority' => '0.3'],
        'blog.index' => ['changefreq' => 'weekly', 'priority' => '0.8'],
    ];

    /**
     * How often a docs page changes and how it ranks.
     *
     * @var array{changefreq: string, priority: string}
     */
    protected const array DOCS = ['changefreq' => 'monthly', 'priority' => '0.6'];

    /**
     * How often a blog post changes and how it ranks.
     *
     * @var array{changefreq: string, priority: string}
     */
    protected const array POSTS = ['changefreq' => 'monthly', 'priority' => '0.7'];

    /**
     * Serve the sitemap, listing every public page in every
     * installed locale with the others as its alternates,
     * followed by every published post of the blog.
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
            ->implode("\n");

        // Every post in the language it was written in, which
        // is not every language, so each carries only the
        // translations it actually has.
        $posts = $this->locales()
            ->flatMap(fn (string $locale): Collection => Post::all($locale))
            ->map(fn (Post $post): string => $this->post($post));

        // The stylesheet turns the sitemap into a readable table
        // for a person who opens it; crawlers ignore it. The
        // sitemap is read by crawlers but never a search result.
        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<?xml-stylesheet type="text/xsl" href="'.e(route('sitemap.stylesheet', absolute: false)).'"?>'."\n".
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n".
            collect([$urls, ...$posts])->filter()->implode("\n")."\n".
            '</urlset>'."\n",
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml', 'X-Robots-Tag' => 'noindex'],
        );
    }

    /**
     * Serve the stylesheet the sitemap points at. It is served
     * from here rather than public/ so it always carries the
     * XML content type a browser needs before applying it.
     */
    public function stylesheet(): Response
    {
        return response(
            File::get(resource_path('xml/sitemap.xsl')),
            Response::HTTP_OK,
            ['Content-Type' => 'text/xsl; charset=UTF-8', 'X-Robots-Tag' => 'noindex'],
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
     * @return array<int, array{url: \Closure(string): string, default: string, changefreq: string, priority: string}>
     */
    protected function pages(): array
    {
        $first = (string) $this->locales()->first();

        return [
            ...array_map(fn (string $name, array $ranking): array => [
                'url' => fn (string $locale): string => route($name, ['locale' => $locale]),
                // The homepage has a route of its own that
                // resolves the language; the documents fall
                // back to the first one.
                'default' => $name === 'home' ? route('home.redirect') : route($name, ['locale' => $first]),
                ...$ranking,
            ], array_keys(self::PAGES), self::PAGES),
            ...array_map(fn (Page $page): array => [
                'url' => $page->url(...),
                'default' => $page->url($first),
                ...self::DOCS,
            ], Page::cases()),
        ];
    }

    /**
     * One page in one locale, carrying every locale of the same
     * page as an alternate so a crawler serves the right
     * language to the right reader.
     *
     * @param  array{url: \Closure(string): string, default: string, changefreq: string, priority: string}  $page
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
            .$this->ranking($page)
            .'</url>';
    }

    /**
     * One post of the blog, carrying the languages it can be
     * read in as its alternates, and when it last changed.
     */
    protected function post(Post $post): string
    {
        $versions = $post->versions();

        // The same post in each language it was translated
        // into, itself included.
        $alternates = collect($versions)
            ->map(fn (string $url, string $locale): string => $this->alternate($locale, $url))
            ->implode('');

        return '<url><loc>'.e($post->url()).'</loc>'
            .'<lastmod>'.($post->updated ?? $post->published)->toDateString().'</lastmod>'
            .$alternates
            .$this->alternate('x-default', $post->alternates()['default'])
            .$this->ranking(self::POSTS)
            .'</url>';
    }

    /**
     * How often a page changes and how it ranks against the
     * others, as a crawler reads them.
     *
     * @param  array{changefreq: string, priority: string}  $page
     */
    protected function ranking(array $page): string
    {
        return '<changefreq>'.$page['changefreq'].'</changefreq>'
            .'<priority>'.$page['priority'].'</priority>';
    }

    /**
     * One hreflang alternate of a page.
     */
    protected function alternate(string $hreflang, string $url): string
    {
        return '<xhtml:link rel="alternate" hreflang="'.e($hreflang).'" href="'.e($url).'"/>';
    }
}
