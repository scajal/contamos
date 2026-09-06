<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Paddle webhook route
|--------------------------------------------------------------------------
|
| Cashier's own routes are disabled (Cashier::ignoreRoutes()) so this
| module can point the webhook at its own controller.
|
*/

Route::post('paddle/webhook', [\App\Modules\Billing\Http\Controllers\PaddleWebhookController::class, '__invoke'])
    ->name('cashier.webhook');

/*
|--------------------------------------------------------------------------
| Team billing routes
|--------------------------------------------------------------------------
|
| These routes correspond to a team's subscription management.
|
*/

Route::as('billing.teams.')
    ->prefix('teams/{team}')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function (): void {
        /*
        |----------------------------------------------------------------------
        | Subscription routes
        |----------------------------------------------------------------------
        */

        Route::get('billing', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'index'])
            ->name('subscriptions.index');

        Route::patch('billing/subscription', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'update'])
            ->name('subscriptions.update');

        Route::delete('billing/subscription', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'destroy'])
            ->name('subscriptions.destroy');

        Route::patch('billing/subscription/resume', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'resume'])
            ->name('subscriptions.resume');

        Route::get('billing/subscription/payment-method', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'paymentMethod'])
            ->name('subscriptions.payment-method');

        /*
        |----------------------------------------------------------------------
        | Transaction routes
        |----------------------------------------------------------------------
        */

        Route::get('billing/transactions/{billingTransaction}/invoice', [\App\Modules\Billing\Http\Controllers\Team\TransactionController::class, 'invoice'])
            ->name('transactions.invoice')
            ->scopeBindings();
    });
