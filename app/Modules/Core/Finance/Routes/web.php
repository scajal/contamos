<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
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
        | Monthly routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the monthly report.
        |
        */

        Route::get('/monthly', [\App\Modules\Core\Finance\Http\Controllers\Team\MonthlyController::class, 'index'])
            ->name('monthly.index');

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

        Route::middleware(EnsureFeaturesAreActive::using(Feature::AddAccounts))->group(function (): void {
            Route::get('/accounts/create', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'create'])
                ->name('accounts.create');

            Route::post('/accounts', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'store'])
                ->name('accounts.store');

            Route::patch('/accounts/{account}/activate', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'activate'])
                ->name('accounts.activate')
                ->scopeBindings();
        });

        Route::get('/accounts/{account}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'edit'])
            ->name('accounts.edit')
            ->scopeBindings();

        Route::patch('/accounts/{account}', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'update'])
            ->name('accounts.update')
            ->scopeBindings();

        Route::patch('/accounts/{account}/deactivate', [\App\Modules\Core\Finance\Http\Controllers\Team\AccountController::class, 'deactivate'])
            ->name('accounts.deactivate')
            ->scopeBindings();

        /*
        |--------------------------------------------------------------------------
        | Tag routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the tag management.
        |
        */

        Route::get('tags', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'index'])
            ->name('tags.index');

        Route::get('tags/create', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'create'])
            ->name('tags.create');

        Route::post('tags', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'store'])
            ->name('tags.store');

        Route::get('tags/{tag}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'edit'])
            ->name('tags.edit')
            ->scopeBindings();

        Route::patch('tags/{tag}', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'update'])
            ->name('tags.update')
            ->scopeBindings();

        Route::patch('tags/{tag}/activate', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'activate'])
            ->name('tags.activate')
            ->scopeBindings();

        Route::patch('tags/{tag}/deactivate', [\App\Modules\Core\Finance\Http\Controllers\Team\TagController::class, 'deactivate'])
            ->name('tags.deactivate')
            ->scopeBindings();

        /*
        |--------------------------------------------------------------------------
        | Transaction routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the transaction management.
        |
        */

        Route::get('/transactions', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'index'])
            ->name('transactions.index');

        Route::get('/transactions/create/{type}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'create'])
            ->whereIn('type', array_column(TransactionType::recordableCases(), 'value'))
            ->name('transactions.create');

        Route::post('/transactions', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'store'])
            ->name('transactions.store');

        Route::get('/transactions/{transaction}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'show'])
            ->name('transactions.show')
            ->scopeBindings();

        Route::get('/transactions/{transaction}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'edit'])
            ->name('transactions.edit')
            ->scopeBindings();

        Route::patch('/transactions/{transaction}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'update'])
            ->name('transactions.update')
            ->scopeBindings();

        Route::delete('/transactions/{transaction}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'destroy'])
            ->name('transactions.destroy')
            ->scopeBindings();

        /*
        |--------------------------------------------------------------------------
        | Transfer routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the transfer management.
        |
        */

        Route::get('/transfers/create', [\App\Modules\Core\Finance\Http\Controllers\Team\TransferController::class, 'create'])
            ->name('transfers.create');

        Route::post('/transfers', [\App\Modules\Core\Finance\Http\Controllers\Team\TransferController::class, 'store'])
            ->name('transfers.store');

        Route::get('/transfers/{transfer}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransferController::class, 'show'])
            ->name('transfers.show')
            ->scopeBindings();

        Route::get('/transfers/{transfer}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\TransferController::class, 'edit'])
            ->name('transfers.edit')
            ->scopeBindings();

        Route::patch('/transfers/{transfer}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransferController::class, 'update'])
            ->name('transfers.update')
            ->scopeBindings();

        Route::delete('/transfers/{transfer}', [\App\Modules\Core\Finance\Http\Controllers\Team\TransferController::class, 'destroy'])
            ->name('transfers.destroy')
            ->scopeBindings();
    });
