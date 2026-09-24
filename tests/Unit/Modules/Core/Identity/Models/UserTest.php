<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Models\User\Preference;
use Illuminate\Support\Facades\URL;

/**
 * Unit tests for \App\Modules\Core\Identity\Models\User.
 */
it('reports whether the user belongs to a team', function (): void {
    [$user, $team] = memberWithTeam();
    $foreignTeam = Team::factory()->create();

    expect($user->belongsToTeam($team))->toBeTrue()
        ->and($user->belongsToTeam($foreignTeam))->toBeFalse();
});

it('does not switch to a team the user does not belong to', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->switchTeam($team))->toBeFalse()
        ->and($user->fresh()?->current_team_id)->toBeNull();
});

it('returns an empty password because authentication is delegated to WorkOS', function (): void {
    $user = User::factory()->make();

    expect($user->getAuthPassword())->toBe('');
});

it('switches to a team the user belongs to and sets the url default', function (): void {
    [$user, $team] = memberWithTeam();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam, ['role' => Role::Owner]);

    expect($user->switchTeam($otherTeam))->toBeTrue()
        ->and($user->fresh()?->current_team_id)->toBe($otherTeam->getKey())
        ->and(URL::getDefaultParameters())->toHaveKey('team', $otherTeam->getRouteKey());
});

it('returns the stored role on a pro team', function (Role $role): void {
    [$user, $team] = memberWithTeam($role);
    subscribeToPro($team);

    expect($user->roleOn($team))->toBe($role);
})->with([
    'owner' => [Role::Owner],
    'admin' => [Role::Admin],
    'member' => [Role::Member],
]);

it('acts as an admin on a free team while keeping the stored role', function (): void {
    [$user, $team] = memberWithTeam(Role::Member);

    expect($user->roleOn($team))->toBe(Role::Admin)
        ->and($user->teams()->whereKey($team->getKey())->sole()->membership?->role)->toBe(Role::Member);
});

it('keeps the owner as the owner on a free team', function (): void {
    [$user, $team] = memberWithTeam();

    expect($user->roleOn($team))->toBe(Role::Owner);
});

it('has no role on a team the user does not belong to', function (): void {
    [$user, $team] = memberWithTeam();
    $foreignTeam = Team::factory()->create();

    expect($user->roleOn($foreignTeam))->toBeNull();
});

it('holds the permissions of its effective role on the team', function (): void {
    [$owner, $team] = memberWithTeam();
    subscribeToPro($team);
    $member = memberOf($team, Role::Member);

    expect($owner->hasTeamPermission($team, Permission::DeleteTeam))->toBeTrue()
        ->and($member->hasTeamPermission($team, Permission::DeleteTeam))->toBeFalse()
        ->and($member->hasTeamPermission($team, Permission::UpdateTeam))->toBeFalse()
        ->and($member->hasTeamPermission($team, Permission::CreateInvitation))->toBeTrue();
});

it('holds no permissions on a team the user does not belong to', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->hasTeamPermission($team, Permission::CreateInvitation))->toBeFalse();
});

it('reads the value the user stored for a preference', function (): void {
    $user = User::factory()->create();
    Preference::factory()->for($user)->appearance('light')->create();

    expect($user->preference(PreferenceKey::Appearance))->toBe('light')
        ->and($user->preference(PreferenceKey::Locale))->toBeNull();
});

it('prefers the stored locale for mail and notifications', function (): void {
    $user = User::factory()->create();
    Preference::factory()->for($user)->locale('es')->create();

    expect($user->preferredLocale())->toBe('es');
});

it('falls back to english for a user who never stored a locale', function (): void {
    $user = User::factory()->create();

    expect($user->preferredLocale())->toBe('en');
});

it('reads the membership that ties the user to a team', function (): void {
    [$user, $team] = memberWithTeam(Role::Admin);

    expect($user->membershipOn($team)?->role)->toBe(Role::Admin)
        ->and($user->membershipOn(Team::factory()->create()))->toBeNull();
});
