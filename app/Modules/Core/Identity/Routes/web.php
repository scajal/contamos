<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamPermission;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
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

        Route::middleware(EnsureFeaturesAreActive::using(Feature::AddTeams))->group(function (): void {
            Route::get('teams/create', [\App\Modules\Core\Identity\Http\Controllers\User\TeamController::class, 'create'])
                ->name('teams.create');

            Route::post('teams', [\App\Modules\Core\Identity\Http\Controllers\User\TeamController::class, 'store'])
                ->name('teams.store');
        });

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
            ->middleware(EnsureFeaturesAreActive::using(Feature::AddMembers))
            ->name('invitations.accept');
    });

Route::as('identity.teams.')
    ->prefix('teams/{team}')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Activity routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the team activity log. The permission runs
        | first, so a member is refused outright instead of being offered an
        | upgrade they may not ask for.
        |
        */

        Route::get('activity', [\App\Modules\Core\Identity\Http\Controllers\Team\ActivityController::class, 'index'])
            ->middleware([
                EnsureTeamPermission::using(Permission::ViewActivity),
                EnsureFeaturesAreActive::using(Feature::ViewActivity),
            ])
            ->name('activity.index');

        Route::get('activity/{activity}', [\App\Modules\Core\Identity\Http\Controllers\Team\ActivityController::class, 'show'])
            ->middleware([
                EnsureTeamPermission::using(Permission::ViewActivity),
                EnsureFeaturesAreActive::using(Feature::ViewActivity),
            ])
            ->name('activity.show')
            ->scopeBindings();

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

        Route::middleware([
            EnsureTeamPermission::using(Permission::UpdateMember),
            EnsureFeaturesAreActive::using(Feature::AssignRoles),
        ])->group(function (): void {
            Route::get('members/{user}/role/edit', [\App\Modules\Core\Identity\Http\Controllers\Team\MemberController::class, 'edit'])
                ->name('members.role.edit')
                ->scopeBindings();

            Route::patch('members/{user}/role', [\App\Modules\Core\Identity\Http\Controllers\Team\MemberController::class, 'update'])
                ->name('members.role.update')
                ->scopeBindings();
        });

        // Removing a member needs the remove permission, but a member
        // removing themself is leaving, which every member may do, so
        // the controller tells the two apart.
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

        Route::middleware([
            EnsureFeaturesAreActive::using(Feature::AddMembers),
            EnsureTeamPermission::using(Permission::CreateInvitation),
        ])->group(function (): void {
            Route::get('invitations/create', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'create'])
                ->name('invitations.create');

            Route::post('invitations', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'store'])
                ->name('invitations.store');
        });

        Route::patch('invitations/{invitation}/resend', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'resend'])
            ->middleware(EnsureTeamPermission::using(Permission::CancelInvitation))
            ->name('invitations.resend')
            ->scopeBindings();

        Route::delete('invitations/{invitation}', [\App\Modules\Core\Identity\Http\Controllers\Team\InvitationController::class, 'destroy'])
            ->middleware(EnsureTeamPermission::using(Permission::CancelInvitation))
            ->name('invitations.destroy')
            ->scopeBindings();

        /*
        |--------------------------------------------------------------------------
        | Setting routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the team settings.
        |
        */

        Route::get('settings', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'index'])
            ->name('settings.index');

        Route::middleware(EnsureTeamPermission::using(Permission::UpdateTeam))->group(function (): void {
            Route::get('settings/edit', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'edit'])
                ->name('settings.edit');

            Route::patch('settings', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'update'])
                ->name('settings.update');
        });

        Route::middleware(EnsureTeamPermission::using(Permission::DeleteTeam))->group(function (): void {
            Route::get('settings/delete', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'delete'])
                ->name('settings.delete');

            Route::delete('settings', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'destroy'])
                ->name('settings.destroy');
        });
    });
