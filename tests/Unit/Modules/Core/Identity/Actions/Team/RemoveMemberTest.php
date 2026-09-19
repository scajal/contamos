<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Actions\Team\RemoveMember;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\Team\RemoveMember.
 */
it('throws when no team was provided', function (): void {
    $user = User::factory()->create();

    expect(fn (): ?Team => (new RemoveMember)->using($user))
        ->toThrow(Exception::class, 'A member cannot be removed without a team.');
});

it('detaches the member from the team', function (): void {
    [$user, $team] = memberWithTeam();

    (new RemoveMember)
        ->for($team)
        ->using($user);

    expect($user->fresh()?->belongsToTeam($team))->toBeFalse();
});

it('moves the member to another of its teams', function (): void {
    [$user, $team] = memberWithTeam();
    $other = Team::factory()->create();
    $user->teams()->attach($other, ['role' => Role::Owner]);

    $next = (new RemoveMember)
        ->for($team)
        ->using($user);

    expect($next?->getKey())->toBe($other->getKey())
        ->and($user->fresh()?->current_team_id)->toBe($other->getKey());
});

it('clears the current team when the member has no team left', function (): void {
    [$user, $team] = memberWithTeam();

    $next = (new RemoveMember)
        ->for($team)
        ->using($user);

    expect($next)->toBeNull()
        ->and($user->fresh()?->current_team_id)->toBeNull();
});

it('keeps the current team of a member removed from another team', function (): void {
    [$user, $team] = memberWithTeam();
    $other = Team::factory()->create();
    $user->teams()->attach($other, ['role' => Role::Owner]);

    $next = (new RemoveMember)
        ->for($other)
        ->using($user);

    expect($next?->getKey())->toBe($team->getKey())
        ->and($user->fresh()?->current_team_id)->toBe($team->getKey());
});
