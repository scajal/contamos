<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\AddTeams;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Features\AddTeams.
 */
it('limits the free plan and leaves the pro plan unlimited', function (): void {
    expect(new AddTeams()->limitOn(Plan::Free))->toBe(1)
        ->and(new AddTeams()->limitOn(Plan::Pro))->toBeNull();
});

it('is active for a user without a free team', function (): void {
    $user = User::factory()->create();

    expect(new AddTeams()->usage($user))->toBe(0)
        ->and(Pennant::for($user)->active(Feature::AddTeams))->toBeTrue();
});

it('counts only the free teams the user belongs to', function (): void {
    [$user, $free] = memberWithTeam();
    $pro = Team::factory()->create();
    $user->teams()->attach($pro, ['role' => Role::Owner]);
    subscribeToPro($pro);

    expect(new AddTeams()->usage($user))->toBe(1)
        ->and(Pennant::for($user)->active(Feature::AddTeams))->toBeFalse();
});

it('is active again once every team of the user is pro', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);

    expect(new AddTeams()->usage($user))->toBe(0)
        ->and(Pennant::for($user)->active(Feature::AddTeams))->toBeTrue();
});
