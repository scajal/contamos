<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelLang\Locales\Facades\Locales;

/*
|--------------------------------------------------------------------------
| Marketing routes
|--------------------------------------------------------------------------
|
| These routes correspond to the public marketing homepage.
|
*/

Route::get('/', [\App\Modules\Marketing\Http\Controllers\HomeController::class, 'redirect'])
    ->name('home.redirect');

Route::get('{locale}', [\App\Modules\Marketing\Http\Controllers\HomeController::class, 'index'])
    ->whereIn('locale', Locales::installed()->pluck('code')->toArray())
    ->name('home');

/*
|--------------------------------------------------------------------------
| Legal routes
|--------------------------------------------------------------------------
|
| These routes correspond to the public legal documents, which live under
| the same locale segment as the homepage so every one of them can be
| linked in either language.
|
*/

Route::whereIn('locale', Locales::installed()->pluck('code')->toArray())->group(function (): void {
    Route::get('{locale}/privacy', [\App\Modules\Marketing\Http\Controllers\LegalController::class, 'privacy'])
        ->name('legal.privacy');

    Route::get('{locale}/terms', [\App\Modules\Marketing\Http\Controllers\LegalController::class, 'terms'])
        ->name('legal.terms');

    Route::get('{locale}/refunds', [\App\Modules\Marketing\Http\Controllers\LegalController::class, 'refunds'])
        ->name('legal.refunds');

    Route::get('{locale}/{document}.md', [\App\Modules\Marketing\Http\Controllers\LegalController::class, 'markdown'])
        ->whereIn('document', ['privacy', 'terms', 'refunds'])
        ->name('legal.markdown');
});

/*
|--------------------------------------------------------------------------
| Docs routes
|--------------------------------------------------------------------------
|
| These routes correspond to the public docs, one page per topic under
| the locale segment, each also served as Markdown at its URL plus .md
| for AI assistants and other readers that are not browsers.
|
*/

Route::whereIn('locale', Locales::installed()->pluck('code')->toArray())->group(function (): void {
    Route::get('{locale}/docs', [\App\Modules\Marketing\Http\Controllers\DocsController::class, 'index'])
        ->name('docs.index');

    Route::get('{locale}/docs/{page}.md', [\App\Modules\Marketing\Http\Controllers\DocsController::class, 'markdown'])
        ->whereIn('page', array_column(\App\Modules\Marketing\Enums\Docs\Page::cases(), 'value'))
        ->name('docs.markdown');

    Route::get('{locale}/docs/{page}', [\App\Modules\Marketing\Http\Controllers\DocsController::class, 'show'])
        ->whereIn('page', array_column(\App\Modules\Marketing\Enums\Docs\Page::cases(), 'value'))
        ->name('docs.show');
});

/*
|--------------------------------------------------------------------------
| Blog routes
|--------------------------------------------------------------------------
|
| These routes correspond to the public blog. Each language keeps its own
| posts under the locale segment, with an RSS feed per language, and each
| post is also served as Markdown at its URL plus .md.
|
*/

Route::whereIn('locale', Locales::installed()->pluck('code')->toArray())->group(function (): void {
    Route::get('{locale}/blog', [\App\Modules\Marketing\Http\Controllers\BlogController::class, 'index'])
        ->name('blog.index');

    Route::get('{locale}/blog/feed.xml', [\App\Modules\Marketing\Http\Controllers\BlogController::class, 'feed'])
        ->name('blog.feed');

    Route::get('{locale}/blog/{slug}.md', [\App\Modules\Marketing\Http\Controllers\BlogController::class, 'markdown'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('blog.markdown');

    Route::get('{locale}/blog/{slug}', [\App\Modules\Marketing\Http\Controllers\BlogController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('blog.show');
});

/*
|--------------------------------------------------------------------------
| Crawler routes
|--------------------------------------------------------------------------
|
| The sitemap, robots.txt and llms-full.txt are served from the route
| table rather than written into public/, so a new public page reaches a
| crawler without anybody remembering to edit a file by hand.
|
*/

Route::get('sitemap.xml', [\App\Modules\Marketing\Http\Controllers\SitemapController::class, 'index'])
    ->name('sitemap');

Route::get('sitemap.xsl', [\App\Modules\Marketing\Http\Controllers\SitemapController::class, 'stylesheet'])
    ->name('sitemap.stylesheet');

Route::get('llms-full.txt', [\App\Modules\Marketing\Http\Controllers\DocsController::class, 'full'])
    ->name('docs.full');

Route::get('robots.txt', [\App\Modules\Marketing\Http\Controllers\SitemapController::class, 'robots'])
    ->name('robots');
