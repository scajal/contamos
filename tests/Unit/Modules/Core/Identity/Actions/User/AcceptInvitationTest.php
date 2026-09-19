<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Actions\User\AcceptInvitation;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\User\AcceptInvitation.
 */
it('throws when no user was provided', function (): void {
    $invitation = TeamInvitation::factory()->create();

    (new AcceptInvitation)->using($invitation);
})->throws(Exception::class, 'An invitation cannot be accepted without a user.');

it('throws when the invitation is no longer pending', function (string $state): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $invitation = TeamInvitation::factory()->{$state}()->create(['email' => 'ada@example.com']);

    (new AcceptInvitation)
        ->for($user)
        ->using($invitation);
})->with(['accepted', 'expired'])->throws(Exception::class, 'The invitation is no longer pending.');

it('throws when the invitation was sent to another email', function (): void {
    $user = User::factory()->create(['email' => 'grace@example.com']);
    $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);

    (new AcceptInvitation)
        ->for($user)
        ->using($invitation);
})->throws(Exception::class, 'The invitation was sent to a different email address.');

it('joins the team, marks the invitation accepted and switches the user to the team', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $invitation = TeamInvitation::factory()->create(['email' => 'Ada@Example.com']);

    $team = (new AcceptInvitation)
        ->for($user)
        ->using($invitation);

    expect($team->is($invitation->team))->toBeTrue()
        ->and($user->belongsToTeam($team))->toBeTrue()
        ->and($user->fresh()?->current_team_id)->toBe($team->getKey())
        ->and($invitation->fresh()?->isAccepted())->toBeTrue();
});

it('keeps a single membership when the user already belongs to the team', function (): void {
    [$user, $team] = memberWithTeam();
    $invitation = TeamInvitation::factory()->for($team)->create(['email' => $user->email]);

    (new AcceptInvitation)
        ->for($user)
        ->using($invitation);

    expect($user->teams()->count())->toBe(1)
        ->and($invitation->fresh()?->isAccepted())->toBeTrue();
});

it('joins the team with the invited role', function (Role $role): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $team = Team::factory()->create();
    subscribeToPro($team);
    $invitation = TeamInvitation::factory()->for($team)->as($role)->create(['email' => 'ada@example.com']);

    (new AcceptInvitation)
        ->for($user)
        ->using($invitation);

    expect($user->roleOn($team))->toBe($role);
})->with([
    'admin' => [Role::Admin],
    'member' => [Role::Member],
]);
