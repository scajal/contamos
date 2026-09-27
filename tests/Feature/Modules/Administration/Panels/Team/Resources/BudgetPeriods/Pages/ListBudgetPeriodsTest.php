<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\ListBudgetPeriods;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\ListBudgetPeriods.
 */
it('lists the category and tag budgets of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $category = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $tag = BudgetPeriod::factory()->forTag(Tag::factory()->for($team)->create())->create();

    Livewire::test(ListBudgetPeriods::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$category, $tag]);
});

it('hides the budgets of another team', function (): void {
    [, $team] = actingOperator();
    $mine = BudgetPeriod::factory()->forCategory(Category::Groceries)->for($team)->create();
    $theirs = BudgetPeriod::factory()->forCategory(Category::Groceries)->for(Team::factory())->create();

    onTeamPanel($team);

    Livewire::test(ListBudgetPeriods::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
