<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\ManageBudgets;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Features\ManageBudgets.
 */
it('is included in the pro plan only', function (): void {
    expect(new ManageBudgets()->includedIn(Plan::Free))->toBeFalse()
        ->and(new ManageBudgets()->includedIn(Plan::Pro))->toBeTrue();
});

it('is inactive for a free team', function (): void {
    [$user, $team] = memberWithTeam();

    expect(Pennant::for($team)->active(Feature::ManageBudgets))->toBeFalse();
});

it('is active for a pro team', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);

    expect(Pennant::for($team)->active(Feature::ManageBudgets))->toBeTrue();
});
