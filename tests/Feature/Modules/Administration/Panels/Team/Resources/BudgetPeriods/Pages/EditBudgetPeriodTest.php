<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\EditBudgetPeriod;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\EditBudgetPeriod.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod
 * and \App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod.
 */
it('changes the amount of a category budget without moving its target', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $period = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create([
        'amount' => '8000',
    ]);

    Livewire::test(EditBudgetPeriod::class, ['record' => $period->getKey()])
        ->assertFormSet(['target_kind' => BudgetTargetKind::Category->value])
        ->fillForm([
            'amount' => '9000',
            'currency_id' => $currency->getKey(),
            'from_month' => $period->from_month->toDateString(),
            'until_month' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $period->refresh();

    expect((float) $period->amount)->toBe(9000.0)
        ->and($period->category)->toBe(Category::Utilities)
        ->and($period->tag_id)->toBeNull();
});

it('changes the amount of a tag budget', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $tag = Tag::factory()->for($team)->create();
    $period = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);

    Livewire::test(EditBudgetPeriod::class, ['record' => $period->getKey()])
        ->assertFormSet(['target_kind' => BudgetTargetKind::Tag->value])
        ->fillForm([
            'amount' => '650',
            'currency_id' => $currency->getKey(),
            'from_month' => $period->from_month->toDateString(),
            'until_month' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $period->fresh()?->amount)->toBe(650.0)
        ->and($period->fresh()?->tag_id)->toBe($tag->getKey());
});

it('removes the budget period', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $period = BudgetPeriod::factory()->forCategory(Category::Food)->for($team)->create();

    Livewire::test(EditBudgetPeriod::class, ['record' => $period->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    expect(BudgetPeriod::query()->find($period->getKey()))->toBeNull();
});
