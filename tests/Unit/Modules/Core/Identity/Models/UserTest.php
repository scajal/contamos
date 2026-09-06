<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
    $user->teams()->attach($otherTeam);

    expect($user->switchTeam($otherTeam))->toBeTrue()
        ->and($user->fresh()?->current_team_id)->toBe($otherTeam->getKey())
        ->and(URL::getDefaultParameters())->toHaveKey('team', $otherTeam->getRouteKey());
});
