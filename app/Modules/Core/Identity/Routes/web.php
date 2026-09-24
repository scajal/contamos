<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership;
use App\Modules\Core\Identity\Http\Middleware\EnsureTeamPermission;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Middleware\ValidateSignature;
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
        | Account routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the user's own account. They are not
        | team-scoped because the account outlives any one team, and they
        | are open on every plan because erasure is never gated.
        |
        */

        Route::get('account', [\App\Modules\Core\Identity\Http\Controllers\User\AccountController::class, 'index'])
            ->name('users.account.index');

        Route::get('account/delete', [\App\Modules\Core\Identity\Http\Controllers\User\AccountController::class, 'delete'])
            ->name('users.account.delete');

        Route::delete('account', [\App\Modules\Core\Identity\Http\Controllers\User\AccountController::class, 'destroy'])
            ->name('users.account.destroy');

        /*
        |--------------------------------------------------------------------------
        | Preference routes
        |--------------------------------------------------------------------------
        |
        | These routes store the language and colour scheme the user picks
        | from the menus, so the choice follows them to every device.
        | Signed-out visitors keep theirs in cookies alone.
        |
        */

        Route::patch('preferences', [\App\Modules\Core\Identity\Http\Controllers\User\PreferenceController::class, 'update'])
            ->name('users.preferences.update');

        /*
        |--------------------------------------------------------------------------
        | Assistant routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the AI assistants connected to the
        | user's account. They are not team-scoped because an assistant
        | acts as the user on every team, and they are open on every
        | plan so a user can always see and cut off what is connected.
        |
        */

        Route::get('assistants', [\App\Modules\Core\Identity\Http\Controllers\User\AssistantController::class, 'index'])
            ->name('users.assistants.index');

        Route::delete('assistants/{application}', [\App\Modules\Core\Identity\Http\Controllers\User\AssistantController::class, 'destroy'])
            ->name('users.assistants.destroy');

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

/*
|--------------------------------------------------------------------------
| Unsubscribe routes
|--------------------------------------------------------------------------
|
| These routes let the recipient of a team email turn that kind of email
| off from the link in it, without signing in. The signature is the only
| credential, and it never expires, so an old email keeps working. The
| team goes by its key, so renaming it does not break the link, and it
| is not scoped to the user, so a user who left still gets a page.
|
*/

Route::as('identity.')
    ->middleware(ValidateSignature::class)
    ->group(function (): void {
        Route::get('notifications/{user}/{team:id}/{type}/unsubscribe', [\App\Modules\Core\Identity\Http\Controllers\NotificationController::class, 'unsubscribe'])
            ->name('notifications.unsubscribe')
            ->withoutScopedBindings();
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
        | upgrade they may not ask for. The listing is not gated on the plan:
        | a team without the feature lands on the page and sees it locked.
        |
        */

        Route::get('activity', [\App\Modules\Core\Identity\Http\Controllers\Team\ActivityController::class, 'index'])
            ->middleware(EnsureTeamPermission::using(Permission::ViewActivity))
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

        // Every Owner and Admin chooses the team emails they
        // receive. A Member receives none, so has nothing to
        // choose.
        Route::middleware(EnsureTeamPermission::using(Permission::ManageNotifications))->group(function (): void {
            Route::get('settings/notifications', [\App\Modules\Core\Identity\Http\Controllers\Team\NotificationController::class, 'edit'])
                ->name('settings.notifications.edit');

            Route::patch('settings/notifications', [\App\Modules\Core\Identity\Http\Controllers\Team\NotificationController::class, 'update'])
                ->name('settings.notifications.update');
        });

        Route::middleware(EnsureTeamPermission::using(Permission::DeleteTeam))->group(function (): void {
            Route::get('settings/delete', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'delete'])
                ->name('settings.delete');

            Route::delete('settings', [\App\Modules\Core\Identity\Http\Controllers\TeamController::class, 'destroy'])
                ->name('settings.destroy');
        });
    });
