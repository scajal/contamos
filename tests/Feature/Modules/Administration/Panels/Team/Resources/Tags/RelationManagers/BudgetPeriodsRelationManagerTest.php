<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Tags\Pages\EditTag;
use App\Modules\Administration\Panels\Team\Resources\Tags\RelationManagers\BudgetPeriodsRelationManager;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Tags\RelationManagers\BudgetPeriodsRelationManager.
 *
 * Budget periods have a resource of their own, and this relation
 * manager keeps the tag-side view of the ones following a tag. Both
 * write through the domain actions that guard overlapping
 * stretches.
 */
it('lists the budget periods of the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create();
    $period = BudgetPeriod::factory()->forTag($tag)->create();

    Livewire::test(BudgetPeriodsRelationManager::class, [
        'ownerRecord' => $tag,
        'pageClass' => EditTag::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$period]);
});

it('opens a budget period on the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);

    Livewire::test(BudgetPeriodsRelationManager::class, [
        'ownerRecord' => $tag,
        'pageClass' => EditTag::class,
    ])
        ->callAction(TestAction::make('create')->table(), [
            'amount' => '500',
            'currency_id' => $currency->getKey(),
            'from_month' => '2026-01-01',
            'until_month' => null,
        ])
        ->assertHasNoActionErrors();

    $period = $tag->budgetPeriods()->sole();

    expect((float) $period->amount)->toBe(500.0)
        ->and($period->currency_id)->toBe($currency->getKey())
        ->and($period->from_month?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($period->until_month)->toBeNull();
});

it('refuses a budget period that overlaps another on the same currency', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
        'from_month' => '2026-01-01',
        'until_month' => null,
    ]);

    Livewire::test(BudgetPeriodsRelationManager::class, [
        'ownerRecord' => $tag,
        'pageClass' => EditTag::class,
    ])
        ->callAction(TestAction::make('create')->table(), [
            'amount' => '500',
            'currency_id' => $currency->getKey(),
            'from_month' => '2026-06-01',
            'until_month' => null,
        ]);

    expect($tag->budgetPeriods()->count())->toBe(1);
})->throws(Exception::class);

it('removes a budget period from the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create();
    $period = BudgetPeriod::factory()->forTag($tag)->create();

    Livewire::test(BudgetPeriodsRelationManager::class, [
        'ownerRecord' => $tag,
        'pageClass' => EditTag::class,
    ])
        ->callAction(TestAction::make('delete')->table($period))
        ->assertNotified();

    expect(BudgetPeriod::query()->find($period->getKey()))->toBeNull();
});
