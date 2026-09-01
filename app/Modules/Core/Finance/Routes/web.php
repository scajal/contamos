<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\Authenticate;
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

Route::as('finance.teams.')
    ->prefix('teams/{team}')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Dashboard routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the dashboard management.
        |
        */

        Route::get('/', [\App\Modules\Core\Finance\Http\Controllers\Team\DashboardController::class, 'index'])
            ->name('dashboard.index');

        /*
        |--------------------------------------------------------------------------
        | Account routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the account management.
        |
        */

        Route::get('/accounts', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'index'])
            ->name('accounts.index');

        Route::get('/accounts/create', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'create'])
            ->name('accounts.create');

        Route::post('/accounts', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'store'])
            ->name('accounts.store');

        Route::get('/accounts/{account}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'edit'])
            ->name('accounts.edit')
            ->scopeBindings();

        Route::put('/accounts/{account}', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'update'])
            ->name('accounts.update')
            ->scopeBindings();

    });
