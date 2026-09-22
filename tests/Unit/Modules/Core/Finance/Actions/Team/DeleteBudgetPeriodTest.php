<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod.
 */
it('throws when a tag was not provided', function (): void {
    (new DeleteBudgetPeriod)->using(BudgetPeriod::factory()->create());
})->throws(Exception::class, 'A budget period cannot be deleted without a tag.');

it('removes the period', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $period = BudgetPeriod::factory()->for($tag)->create();

    (new DeleteBudgetPeriod)->for($tag)->using($period);

    expect(BudgetPeriod::query()->find($period->getKey()))->toBeNull();
});
