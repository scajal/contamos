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
