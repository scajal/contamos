<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Finance routes
|--------------------------------------------------------------------------
|
| These routes correspond to the finance management.
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

    Route::put('currencies/{currency}', [\App\Modules\Core\Catalog\Http\Controllers\CurrencyController::class, 'update'])
        ->middleware(HandlePrecognitiveRequests::class)
        ->name('currencies.update');
});
