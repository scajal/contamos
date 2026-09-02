<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Catalog routes
|--------------------------------------------------------------------------
|
| These routes correspond to the catalog management.
|
*/

Route::as('catalog.')->middleware([
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

    Route::get('currencies', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'index'])
        ->name('currencies.index');

    Route::get('currencies/create', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'create'])
        ->name('currencies.create');

    Route::post('currencies', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'store'])
        ->middleware(HandlePrecognitiveRequests::class)
        ->name('currencies.store');

    Route::get('currencies/{currency}/edit', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'edit'])
        ->name('currencies.edit');

    Route::patch('currencies/{currency}', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'update'])
        ->middleware(HandlePrecognitiveRequests::class)
        ->name('currencies.update');

    Route::patch('currencies/{currency}/activate', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'activate'])
        ->name('currencies.activate');

    Route::patch('currencies/{currency}/deactivate', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'deactivate'])
        ->name('currencies.deactivate');

    /*
    |--------------------------------------------------------------------------
    | Exchange rate routes
    |--------------------------------------------------------------------------
    |
    | These routes correspond to the exchange rate management.
    |
    */

    Route::get('currencies/{currency}/exchange-rates', [\App\Modules\Core\Catalog\Http\Controllers\Currency\ExchangeRateController::class, 'index'])
        ->name('currencies.exchange-rates.index')
        ->scopeBindings();

    Route::get('currencies/{currency}/exchange-rates/create', [\App\Modules\Core\Catalog\Http\Controllers\Currency\ExchangeRateController::class, 'create'])
        ->name('currencies.exchange-rates.create')
        ->scopeBindings();
});
