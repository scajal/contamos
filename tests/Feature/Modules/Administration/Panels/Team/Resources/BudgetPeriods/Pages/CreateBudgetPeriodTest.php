<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\CreateBudgetPeriod;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages\CreateBudgetPeriod.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod,
 * which guards the target and the overlapping stretches.
 */
it('opens a budget period on a category', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);

    Livewire::test(CreateBudgetPeriod::class)
        // Picking the kind empties the other picker, so the
        // target is chosen before the rest is filled.
        ->fillForm(['target_kind' => BudgetTargetKind::Category->value])
        ->fillForm([
            'amount' => '8000',
            'category' => Category::Utilities->value,
            'currency_id' => $currency->getKey(),
            'from_month' => '2026-01-01',
            'until_month' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $period = BudgetPeriod::query()->sole();

    expect($period->category)->toBe(Category::Utilities)
        ->and($period->tag_id)->toBeNull()
        ->and($period->team_id)->toBe($team->getKey())
        ->and((float) $period->amount)->toBe(8000.0);
});

it('opens a budget period on a tag of the team', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $tag = Tag::factory()->for($team)->create();

    Livewire::test(CreateBudgetPeriod::class)
        ->fillForm(['target_kind' => BudgetTargetKind::Tag->value])
        ->fillForm([
            'amount' => '500',
            'currency_id' => $currency->getKey(),
            'from_month' => '2026-01-01',
            'tag_id' => $tag->getKey(),
            'until_month' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $period = BudgetPeriod::query()->sole();

    expect($period->tag_id)->toBe($tag->getKey())
        ->and($period->category)->toBeNull()
        ->and($period->team_id)->toBe($team->getKey());
});

it('refuses a category budget overlapping another on the same currency', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->for($currency)->create([
        'from_month' => '2026-01-01',
        'until_month' => null,
    ]);

    Livewire::test(CreateBudgetPeriod::class)
        ->fillForm(['target_kind' => BudgetTargetKind::Category->value])
        ->fillForm([
            'amount' => '9000',
            'category' => Category::Utilities->value,
            'currency_id' => $currency->getKey(),
            'from_month' => '2026-06-01',
            'until_month' => null,
        ])
        ->call('create');

    expect(BudgetPeriod::query()->count())->toBe(1);
})->throws(Exception::class);
