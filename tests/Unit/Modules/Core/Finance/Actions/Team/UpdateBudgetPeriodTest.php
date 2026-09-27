<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetAlert;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod.
 */
it('throws when a team was not provided', function (): void {
    new UpdateBudgetPeriod()->using(BudgetPeriod::factory()->create(), ['amount' => '50']);
})->throws(Exception::class, 'A budget period cannot be updated without a team.');

it('updates the period amount', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $period = BudgetPeriod::factory()->forTag($tag)->create(['amount' => '100']);

    $updated = new UpdateBudgetPeriod()->for($tag->team)->using($period, ['amount' => '50']);

    expect((float) $updated->amount)->toBe(50.0);
});

it('updates a category period without touching its target', function (): void {
    $team = Team::factory()->create();
    $period = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create(['amount' => '100']);

    $updated = new UpdateBudgetPeriod()->for($team)->using($period, [
        'amount' => '8000',
        'until_month' => '2026-12-01',
    ]);

    expect((float) $updated->amount)->toBe(8000.0)
        ->and($updated->category)->toBe(Category::Utilities)
        ->and($updated->tag_id)->toBeNull()
        ->and($updated->until_month?->toDateString())->toBe('2026-12-01');
});

it('moves a tag period under a category period', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $period = BudgetPeriod::factory()->forTag()->for($team)->create(['currency_id' => $parent->currency_id]);

    $updated = new UpdateBudgetPeriod()->for($team)->using($period, ['parent_id' => $parent->getKey()]);

    expect($updated->parent_id)->toBe($parent->getKey())
        ->and($updated->scopedCategory())->toBe(Category::Utilities);
});

it('takes a sub-budget out from under its parent when the parent is cleared', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $period = BudgetPeriod::factory()->within($parent)->create();

    $updated = new UpdateBudgetPeriod()->for($team)->using($period, ['parent_id' => null]);

    expect($updated->parent_id)->toBeNull();
});

it('keeps the parent when the change says nothing about it', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $period = BudgetPeriod::factory()->within($parent)->create(['amount' => '100']);

    $updated = new UpdateBudgetPeriod()->for($team)->using($period, ['amount' => '50']);

    expect($updated->parent_id)->toBe($parent->getKey());
});

it('throws when a currency change leaves the parent behind', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $period = BudgetPeriod::factory()->within($parent)->create();

    new UpdateBudgetPeriod()->for($team)->using($period, ['currency_id' => Currency::factory()->create()->getKey()]);
})->throws(Exception::class, 'A budget period cannot be nested under a budget in another currency.');

it('throws when the new months overlap another period on the category', function (): void {
    $team = Team::factory()->create();
    $running = BudgetPeriod::factory()->forCategory(Category::Groceries)->for($team)->create([
        'from_month' => '2026-01-01',
        'until_month' => '2026-03-01',
    ]);
    BudgetPeriod::factory()->forCategory(Category::Groceries)->for($team)->create([
        'currency_id' => $running->currency_id,
        'from_month' => '2026-04-01',
        'until_month' => null,
    ]);

    new UpdateBudgetPeriod()->for($team)->using($running, ['until_month' => '2026-06-01']);
})->throws(Exception::class, 'A budget period cannot overlap another one on the same target and currency.');

/**
 * A running Food budget of 100 warning at 80%, whose month
 * spent 90 and already sent the alerts of the given kinds.
 *
 * @param  array<int, \App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind>  $sent
 */
function alertedFoodBudget(array $sent): BudgetPeriod
{
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency);
    Transaction::factory()->expense()->for($account)->for($team)->create([
        'amount' => '90',
        'category' => Category::Groceries,
        'currency_id' => $currency->getKey(),
        'occurred_at' => CarbonImmutable::now(),
    ]);
    $period = BudgetPeriod::factory()->forCategory(Category::Groceries)->running()->for($team)->withWarningThreshold(80)->create([
        'amount' => '100',
        'currency_id' => $currency->getKey(),
    ]);

    foreach ($sent as $kind) {
        BudgetAlert::factory()->for($period)->create(['kind' => $kind]);
    }

    return $period;
}

it('re-arms every alert whose threshold a raised amount no longer meets', function (string $amount, array $kept): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $period = alertedFoodBudget([BudgetAlertKind::Warning, BudgetAlertKind::Over]);

    new UpdateBudgetPeriod()->for($period->team)->using($period, ['amount' => $amount]);

    expect($period->alerts()->orderBy('kind')->get()->pluck('kind')->all())->toBe($kept);
})->with([
    'under both thresholds' => ['200', []],
    'under the amount, still past the warning' => ['110', [BudgetAlertKind::Warning]],
]);

it('keeps the alerts when the amount is raised but the month still meets them', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $period = alertedFoodBudget([BudgetAlertKind::Warning]);

    new UpdateBudgetPeriod()->for($period->team)->using($period, ['amount' => '105']);

    expect($period->alerts()->count())->toBe(1);
});

it('keeps the alerts when the amount is lowered', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $period = alertedFoodBudget([BudgetAlertKind::Warning]);
    $period->update(['amount' => '1000']);

    new UpdateBudgetPeriod()->for($period->team)->using($period, ['amount' => '500']);

    expect($period->alerts()->count())->toBe(1);
});

it('leaves the alerts of past months alone', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $period = alertedFoodBudget([]);
    BudgetAlert::factory()->for($period)->create(['month' => CarbonImmutable::parse('2026-08-01')]);

    new UpdateBudgetPeriod()->for($period->team)->using($period, ['amount' => '1000']);

    expect($period->alerts()->count())->toBe(1);
});

it('re-arms nothing on a period that does not run this month', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    $period = alertedFoodBudget([BudgetAlertKind::Warning]);

    new UpdateBudgetPeriod()->for($period->team)->using($period, ['amount' => '1000', 'from_month' => '2026-10-01']);

    expect($period->alerts()->count())->toBe(1);
});
