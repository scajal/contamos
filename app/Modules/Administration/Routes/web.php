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

        Route::get('currencies', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'index'])
            ->name('currencies.index');

        Route::get('currencies/create', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'create'])
            ->name('currencies.create');

        Route::post('currencies', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'store'])
            ->middleware(HandlePrecognitiveRequests::class)
            ->name('currencies.store');

        Route::get('currencies/{currency}/edit', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'edit'])
            ->name('currencies.edit');

        Route::patch('currencies/{currency}', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'update'])
            ->middleware(HandlePrecognitiveRequests::class)
            ->name('currencies.update');

        Route::patch('currencies/{currency}/activate', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'activate'])
            ->name('currencies.activate');

        Route::patch('currencies/{currency}/deactivate', [\App\Modules\Administration\Http\Controllers\CurrencyController::class, 'deactivate'])
            ->name('currencies.deactivate');

        /*
        |--------------------------------------------------------------------------
        | Quote routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the quote management.
        |
        */

        Route::get('currencies/{currency}/quotes', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'index'])
            ->name('currencies.quotes.index')
            ->scopeBindings();

        Route::get('currencies/{currency}/quotes/create', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'create'])
            ->name('currencies.quotes.create')
            ->scopeBindings();

        Route::post('currencies/{currency}/quotes', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'store'])
            ->middleware(HandlePrecognitiveRequests::class)
            ->name('currencies.quotes.store')
            ->scopeBindings();

        Route::delete('currencies/{currency}/quotes/{quote}', [\App\Modules\Administration\Http\Controllers\Currency\QuoteController::class, 'destroy'])
            ->name('currencies.quotes.destroy')
            ->scopeBindings();
    });
