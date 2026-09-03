<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Administration routes
|--------------------------------------------------------------------------
|
| These routes correspond to the administration management.
|
*/

Route::as('administration.')
    ->prefix('administration')
    ->middleware([
        Authenticate::class,
        ValidateSessionWithWorkOS::class,
    ])->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Currency routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the currency management.
        |
        */

        Route::get('catalog/currencies', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'index'])
            ->name('catalog.currencies.index');

        Route::get('catalog/currencies/create', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'create'])
            ->name('catalog.currencies.create');

        Route::post('catalog/currencies', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'store'])
            ->middleware(HandlePrecognitiveRequests::class)
            ->name('catalog.currencies.store');

        Route::get('catalog/currencies/{currency}/edit', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'edit'])
            ->name('catalog.currencies.edit');

        Route::patch('catalog/currencies/{currency}', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'update'])
            ->middleware(HandlePrecognitiveRequests::class)
            ->name('catalog.currencies.update');

        Route::patch('catalog/currencies/{currency}/activate', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'activate'])
            ->name('catalog.currencies.activate');

        Route::patch('catalog/currencies/{currency}/deactivate', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'deactivate'])
            ->name('catalog.currencies.deactivate');

        /*
        |--------------------------------------------------------------------------
        | Quote routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the quote management.
        |
        */

        Route::get('catalog/currencies/{currency}/quotes', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'index'])
            ->name('catalog.currencies.quotes.index')
            ->scopeBindings();

        Route::get('catalog/currencies/{currency}/quotes/create', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'create'])
            ->name('catalog.currencies.quotes.create')
            ->scopeBindings();

        Route::post('catalog/currencies/{currency}/quotes', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'store'])
            ->middleware(HandlePrecognitiveRequests::class)
            ->name('catalog.currencies.quotes.store')
            ->scopeBindings();

        Route::delete('catalog/currencies/{currency}/quotes/{quote}', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'destroy'])
            ->name('catalog.currencies.quotes.destroy')
            ->scopeBindings();

        /*
        |--------------------------------------------------------------------------
        | User routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the user management.
        |
        */

        Route::get('identity/users', [\App\Modules\Administration\Http\Controllers\UserController::class, 'index'])
            ->name('identity.users.index');
    });
