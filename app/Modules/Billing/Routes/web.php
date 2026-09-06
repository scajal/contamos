<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
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
        |--------------------------------------------------------------------------
        | Subscription routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the subscription management.
        |
        */

        Route::get('billing', [\App\Modules\Billing\Http\Controllers\Team\SubscriptionController::class, 'show'])
            ->name('subscription.show');
    });
