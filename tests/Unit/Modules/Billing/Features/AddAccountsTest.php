<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\AddAccounts;
use App\Modules\Core\Finance\Models\Team\Account;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Features\AddAccounts.
 */
it('limits the free plan and leaves the pro plan unlimited', function (): void {
    expect(new AddAccounts()->limitOn(Plan::Free))->toBe(3)
        ->and(new AddAccounts()->limitOn(Plan::Pro))->toBeNull();
});

it('is active while the free team is under its account limit', function (): void {
    [$user, $team] = memberWithTeam();
    Account::factory()->count(2)->active()->for($team)->create();

    expect(new AddAccounts()->usage($team))->toBe(2)
        ->and(Pennant::for($team)->active(Feature::AddAccounts))->toBeTrue();
});

it('is inactive once the free team reaches its account limit', function (): void {
    [$user, $team] = memberWithTeam();
    Account::factory()->count(3)->active()->for($team)->create();

    expect(Pennant::for($team)->active(Feature::AddAccounts))->toBeFalse();
});

it('ignores deactivated accounts', function (): void {
    [$user, $team] = memberWithTeam();
    Account::factory()->count(2)->active()->for($team)->create();
    Account::factory()->count(3)->inactive()->for($team)->create();

    expect(new AddAccounts()->usage($team))->toBe(2)
        ->and(Pennant::for($team)->active(Feature::AddAccounts))->toBeTrue();
});

it('is always active for a pro team', function (): void {
    [$user, $team] = memberWithTeam();
    subscribeToPro($team);
    Account::factory()->count(5)->active()->for($team)->create();

    expect(Pennant::for($team)->active(Feature::AddAccounts))->toBeTrue();
});
