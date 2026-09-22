<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod.
 */
it('throws when a tag was not provided', function (): void {
    (new UpdateBudgetPeriod)->using(BudgetPeriod::factory()->create(), ['amount' => '50']);
})->throws(Exception::class, 'A budget period cannot be updated without a tag.');

it('updates the period amount', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $period = BudgetPeriod::factory()->for($tag)->create(['amount' => '100']);

    $updated = (new UpdateBudgetPeriod)->for($tag)->using($period, ['amount' => '50']);

    expect((float) $updated->amount)->toBe(50.0);
});
