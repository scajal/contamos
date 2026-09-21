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
});

/*
|--------------------------------------------------------------------------
| Crawler routes
|--------------------------------------------------------------------------
|
| The sitemap and robots.txt are served from the route table rather than
| written into public/, so a new public page reaches a crawler without
| anybody remembering to edit XML by hand.
|
*/

Route::get('sitemap.xml', [\App\Modules\Marketing\Http\Controllers\SitemapController::class, 'index'])
    ->name('sitemap');

Route::get('robots.txt', [\App\Modules\Marketing\Http\Controllers\SitemapController::class, 'robots'])
    ->name('robots');
