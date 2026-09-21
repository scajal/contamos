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
        | Budget routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the per-tag budget management.
        |
        */

        // The listing itself stays reachable on every plan so a
        // team that downgrades sees the locked upgrade state
        // rather than losing the page.
        Route::get('budgets', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'index'])
            ->name('budgets.index');

        Route::middleware(EnsureFeaturesAreActive::using(Feature::ManageBudgets))->group(function (): void {
            Route::get('budgets/create', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'create'])
                ->name('budgets.create');

            Route::post('budgets', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'store'])
                ->name('budgets.store');

            Route::get('budgets/{budget}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'edit'])
                ->name('budgets.edit')
                ->scopeBindings();

            Route::patch('budgets/{budget}', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'update'])
                ->name('budgets.update')
                ->scopeBindings();

            Route::patch('budgets/{budget}/pause', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'pause'])
                ->name('budgets.pause')
                ->scopeBindings();

            Route::patch('budgets/{budget}/resume', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'resume'])
                ->name('budgets.resume')
                ->scopeBindings();

            Route::delete('budgets/{budget}', [\App\Modules\Core\Finance\Http\Controllers\Team\BudgetController::class, 'destroy'])
                ->name('budgets.destroy')
                ->scopeBindings();
        });

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

        // Declared before the {transaction} route so the literal
        // segment is not eaten by the model binding.
        Route::get('/transactions/export', [\App\Modules\Core\Finance\Http\Controllers\Team\TransactionController::class, 'export'])
            ->middleware('throttle:10,1')
            ->name('transactions.export');

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

        /*
        |--------------------------------------------------------------------------
        | Recurring rule routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the recurring rule management.
        |
        */

        Route::get('/recurring-rules', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'index'])
            ->name('recurring-rules.index');

        Route::get('/recurring-rules/{recurringRule}/edit', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'edit'])
            ->name('recurring-rules.edit')
            ->scopeBindings();

        Route::delete('/recurring-rules/{recurringRule}', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'destroy'])
            ->name('recurring-rules.destroy')
            ->scopeBindings();

        Route::delete('/recurring-rules/{recurringRule}/undo', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'undo'])
            ->name('recurring-rules.undo')
            ->scopeBindings();

        // A team whose plan does not include recurring transactions
        // keeps its rules listed and may delete them, but cannot add
        // to or restart what would never generate.
        Route::middleware(EnsureFeaturesAreActive::using(Feature::RecurTransactions))->group(function (): void {
            Route::get('/recurring-rules/create', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'create'])
                ->name('recurring-rules.create');

            Route::post('/recurring-rules', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'store'])
                ->name('recurring-rules.store');

            Route::patch('/recurring-rules/{recurringRule}', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'update'])
                ->name('recurring-rules.update')
                ->scopeBindings();

            Route::patch('/recurring-rules/{recurringRule}/pause', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'pause'])
                ->name('recurring-rules.pause')
                ->scopeBindings();

            Route::patch('/recurring-rules/{recurringRule}/resume', [\App\Modules\Core\Finance\Http\Controllers\Team\RecurringRuleController::class, 'resume'])
                ->name('recurring-rules.resume')
                ->scopeBindings();
        });
    });
