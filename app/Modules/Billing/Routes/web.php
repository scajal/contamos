<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamPermission;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Billing routes
|--------------------------------------------------------------------------
|
| These routes correspond to the billing management.
|
*/

Route::as('billing.teams.')
    ->prefix('teams/{team}')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function (): void {
        /*
        |------------------------------------------------------------------------
        | Subscriptions
        |------------------------------------------------------------------------
        |
        | These routes correspond to the subscription management.
        |
        */

        // Everything that changes or reveals what the team pays is
        // the owner's business.
        Route::middleware(EnsureTeamPermission::using(Permission::ManageBilling))->group(function (): void {
            Route::get('billing', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'index'])
                ->name('subscriptions.index');

            Route::get('billing/customer-portal', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'redirectToCustomerPortal'])
                ->name('subscriptions.redirect-to-customer-portal');

            Route::get('billing/subscribe/{cycle}', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'subscribe'])
                ->whereIn('cycle', BillingCycle::cases())
                ->name('subscriptions.subscribe');

            Route::get('billing/transactions/{billingTransaction}/invoice', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'invoice'])
                ->name('subscriptions.invoice')
                ->scopeBindings();
        });

        // The upgrade prompt is where a member lands after hitting a
        // plan limit, so every member reaches it. It offers checkout
        // to whoever manages billing and points the rest at the owner.
        Route::get('billing/upgrade', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'upgrade'])
            ->name('subscriptions.upgrade');
    });
