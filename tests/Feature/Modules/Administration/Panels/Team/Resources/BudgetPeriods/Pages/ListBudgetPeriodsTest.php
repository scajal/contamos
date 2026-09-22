<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\ListBudgetPeriods;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
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
    $mine = BudgetPeriod::factory()->forCategory(Category::Food)->for($team)->create();
    $theirs = BudgetPeriod::factory()->forCategory(Category::Food)->for(Team::factory())->create();

    onTeamPanel($team);

    Livewire::test(ListBudgetPeriods::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('filters the budgets by target kind', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $category = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $tag = BudgetPeriod::factory()->forTag(Tag::factory()->for($team)->create())->create();

    Livewire::test(ListBudgetPeriods::class)
        ->loadTable()
        ->filterTable('target_kind', BudgetTargetKind::Category->value)
        ->assertCanSeeTableRecords([$category])
        ->assertCanNotSeeTableRecords([$tag]);
});

it('filters the budgets by currency', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->fiat()->create();
    $matching = BudgetPeriod::factory()->forCategory(Category::Food)->for($team)->for($currency)->create();
    $other = BudgetPeriod::factory()->forCategory(Category::Transport)->for($team)->create();

    Livewire::test(ListBudgetPeriods::class)
        ->loadTable()
        ->filterTable('currency_id', $currency->getKey())
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters the budgets by status', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $running = BudgetPeriod::factory()->running()->forCategory(Category::Food)->for($team)->create();
    $scheduled = BudgetPeriod::factory()->scheduled()->forCategory(Category::Housing)->for($team)->create();
    $paused = BudgetPeriod::factory()->paused()->forCategory(Category::Transport)->for($team)->create();
    $objective = BudgetPeriod::factory()
        ->singleMonth(CarbonImmutable::now()->subMonths(2))
        ->forCategory(Category::Health)
        ->for($team)
        ->create();

    $page = Livewire::test(ListBudgetPeriods::class)->loadTable();

    $page->filterTable('status', BudgetPeriodStatus::Running->value)
        ->assertCanSeeTableRecords([$running])
        ->assertCanNotSeeTableRecords([$scheduled, $paused, $objective]);

    $page->filterTable('status', BudgetPeriodStatus::Scheduled->value)
        ->assertCanSeeTableRecords([$scheduled])
        ->assertCanNotSeeTableRecords([$running]);

    $page->filterTable('status', BudgetPeriodStatus::Paused->value)
        ->assertCanSeeTableRecords([$paused])
        ->assertCanNotSeeTableRecords([$running, $objective]);

    $page->filterTable('status', BudgetPeriodStatus::Ended->value)
        ->assertCanSeeTableRecords([$objective])
        ->assertCanNotSeeTableRecords([$running, $paused]);
});
