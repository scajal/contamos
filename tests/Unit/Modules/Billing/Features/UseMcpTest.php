<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\UseMcp;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Features\UseMcp.
 */
it('is included in every plan', function (): void {
    expect(new UseMcp()->includedIn(Plan::Free))->toBeTrue()
        ->and(new UseMcp()->includedIn(Plan::Pro))->toBeTrue();
});

it('is active for a free team', function (): void {
    [$user, $team] = memberWithTeam();

    expect(Pennant::for($team)->active(Feature::UseMcp))->toBeTrue();
});

it('is active for a pro team', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);

    expect(Pennant::for($team)->active(Feature::UseMcp))->toBeTrue();
});
