<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Middleware\EnsureWithinPlanLimit;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Identity routes
|--------------------------------------------------------------------------
|
| These routes correspond to the identity management.
|
*/

Route::as('identity.')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Team routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the team management.
        |
        */

        Route::get('teams/create', [\App\Modules\Core\Identity\Http\Controllers\User\TeamController::class, 'create'])
            ->name('teams.create');

        Route::post('teams', [\App\Modules\Core\Identity\Http\Controllers\User\TeamController::class, 'store'])
            ->name('teams.store');

        /*
        |--------------------------------------------------------------------------
        | Invitation routes
        |--------------------------------------------------------------------------
        |
        | These routes let an invitee review and accept an invitation. They are
        | not team-scoped because the invitee is not a member yet.
        |
        */

        Route::get('invitations/{invitation}', [\App\Modules\Core\Identity\Http\Controllers\InvitationController::class, 'show'])
            ->name('invitations.show');

        Route::post('invitations/{invitation}/accept', [\App\Modules\Core\Identity\Http\Controllers\InvitationController::class, 'accept'])
            ->name('invitations.accept');
    });

Route::as('identity.teams.')
    ->prefix('teams/{team}')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Member routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the team member management.
        |
        */

        Route::get('members', [\App\Modules\Core\Identity\Http\Controllers\Team\MemberController::class, 'index'])
            ->name('members.index');

        Route::delete('members/{user}', [\App\Modules\Core\Identity\Http\Controllers\Team\MemberController::class, 'destroy'])
            ->name('members.destroy')
            ->scopeBindings();

        /*
        |--------------------------------------------------------------------------
        | Invitation routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the team invitation management.
        |
        */

        Route::get('invitations', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'index'])
            ->name('invitations.index');

        Route::get('invitations/create', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'create'])
            ->middleware(EnsureWithinPlanLimit::class.':members')
            ->name('invitations.create');

        Route::post('invitations', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'store'])
            ->middleware(EnsureWithinPlanLimit::class.':members')
            ->name('invitations.store');

        Route::patch('invitations/{invitation}/resend', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'resend'])
            ->name('invitations.resend')
            ->scopeBindings();

        Route::delete('invitations/{invitation}', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'destroy'])
            ->name('invitations.destroy')
            ->scopeBindings();
    });
