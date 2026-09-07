<?php

declare(strict_types=1);

use App\Modules\Support\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

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
    ->whereIn('locale', SetLocale::LOCALES)
    ->name('home');
