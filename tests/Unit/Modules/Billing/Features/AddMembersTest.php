<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\AddMembers;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Features\AddMembers.
 */
it('limits the free plan and leaves the pro plan unlimited', function (): void {
    expect(new AddMembers()->limitOn(Plan::Free))->toBe(2)
        ->and(new AddMembers()->limitOn(Plan::Pro))->toBeNull();
});

it('counts only the members as usage', function (): void {
    [$user, $team] = memberWithTeam();
    TeamInvitation::factory()->count(5)->for($team)->create();

    expect(new AddMembers()->usage($team))->toBe(1)
        ->and(Pennant::for($team)->active(Feature::AddMembers))->toBeTrue();
});

it('is inactive once the free team reaches its member limit', function (): void {
    [$user, $team] = memberWithTeam();
    $team->users()->attach(User::factory()->create());

    expect(new AddMembers()->usage($team))->toBe(2)
        ->and(Pennant::for($team)->active(Feature::AddMembers))->toBeFalse();
});

it('is always active for a pro team', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);
    $team->users()->attach(User::factory()->count(4)->create());

    expect(Pennant::for($team)->active(Feature::AddMembers))->toBeTrue();
});
