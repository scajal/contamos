<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\ViewHistory;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Feature as Pennant;

/**
 * Unit tests for \App\Modules\Billing\Features\ViewHistory.
 */
it('limits the free plan to six months and leaves the pro plan unlimited', function (): void {
    expect(new ViewHistory()->limitOn(Plan::Free))->toBe(6)
        ->and(new ViewHistory()->limitOn(Plan::Pro))->toBeNull();
});

it('counts a single month for a team without transactions', function (): void {
    [$user, $team] = memberWithTeam();

    expect(new ViewHistory()->usage($team))->toBe(1);
});

it('counts the months since the first transaction, both ends included', function (): void {
    [$user, $team] = memberWithTeam();
    $account = Account::factory()->active()->for($team)->create();

    Transaction::factory()->for($account)->for($team)->create([
        'occurred_at' => CarbonImmutable::now()->subMonths(3),
    ]);

    expect(new ViewHistory()->usage($team))->toBe(4);
});

it('counts by month rather than by transaction', function (): void {
    [$user, $team] = memberWithTeam();
    $account = Account::factory()->active()->for($team)->create();

    Transaction::factory()->count(3)->for($account)->for($team)->create([
        'occurred_at' => CarbonImmutable::now()->subMonth()->startOfMonth()->addDays(5),
    ]);

    expect(new ViewHistory()->usage($team))->toBe(2);
});

it('is inactive for a free team and active for a pro team', function (): void {
    [$user, $team] = memberWithTeam();

    expect(new ViewHistory()->resolve($team))->toBeFalse()
        ->and(Pennant::for($team)->active(Feature::ViewHistory))->toBeFalse();

    subscribeToPro($team);
    Pennant::for($team)->forget(Feature::ViewHistory);

    expect(new ViewHistory()->resolve($team))->toBeTrue()
        ->and(Pennant::for($team)->active(Feature::ViewHistory))->toBeTrue();
});
