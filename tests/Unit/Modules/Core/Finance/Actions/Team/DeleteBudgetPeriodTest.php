<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod.
 */
it('throws when a team was not provided', function (): void {
    new DeleteBudgetPeriod()->using(BudgetPeriod::factory()->create());
})->throws(Exception::class, 'A budget period cannot be deleted without a team.');

it('removes the period', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $period = BudgetPeriod::factory()->forTag($tag)->create();

    new DeleteBudgetPeriod()->for($tag->team)->using($period);

    expect(BudgetPeriod::query()->find($period->getKey()))->toBeNull();
});

it('removes a category period', function (): void {
    $team = Team::factory()->create();
    $period = BudgetPeriod::factory()->forCategory(Category::Housing)->for($team)->create();

    new DeleteBudgetPeriod()->for($team)->using($period);

    expect(BudgetPeriod::query()->find($period->getKey()))->toBeNull();
});
