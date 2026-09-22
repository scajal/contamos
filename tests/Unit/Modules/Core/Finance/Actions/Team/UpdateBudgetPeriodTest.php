<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;

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

it('throws when the new months overlap another period on the category', function (): void {
    $team = Team::factory()->create();
    $running = BudgetPeriod::factory()->forCategory(Category::Food)->for($team)->create([
        'from_month' => '2026-01-01',
        'until_month' => '2026-03-01',
    ]);
    BudgetPeriod::factory()->forCategory(Category::Food)->for($team)->create([
        'currency_id' => $running->currency_id,
        'from_month' => '2026-04-01',
        'until_month' => null,
    ]);

    new UpdateBudgetPeriod()->for($team)->using($running, ['until_month' => '2026-06-01']);
})->throws(Exception::class, 'A budget period cannot overlap another one on the same target and currency.');
