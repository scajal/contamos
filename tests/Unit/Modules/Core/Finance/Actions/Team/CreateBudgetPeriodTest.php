<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod.
 */
it('throws when a team was not provided', function (): void {
    new CreateBudgetPeriod()->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period cannot be created without a team.');

it('throws when a target was not provided', function (): void {
    $team = Team::factory()->create();

    new CreateBudgetPeriod()->for($team)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period cannot be written without a category or a tag.');

it('throws when the tag belongs to another team', function (): void {
    $team = Team::factory()->create();
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period cannot follow a tag of another team.');

it('throws when the first month is missing', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => null,
    ]);
})->throws(Exception::class, 'A budget period cannot be created without a first month.');

it('throws when a month is not a date', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => 2026,
    ]);
})->throws(Exception::class, 'A budget period month must be a date.');

it('throws when the warning threshold is not a whole number', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
        'warning_threshold' => '12.5',
    ]);
})->throws(Exception::class, 'A budget period warning threshold must be a whole number.');

it('throws when the currency is not named', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => 1,
        'from_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period must name the currency it is expressed in.');

it('throws when the stretch ends before it starts', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-06-01',
        'until_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period cannot end before it starts.');

it('opens a period from an immutable month', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $currency = Currency::factory()->create();

    $period = new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'from_month' => CarbonImmutable::parse('2026-01-15'),
    ]);

    expect($period->from_month->toDateString())->toBe('2026-01-01')
        ->and($period->until_month)->toBeNull()
        ->and($period->team_id)->toBe($tag->team_id);
});

it('opens a stretch that closes before a later period starts', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $currency = Currency::factory()->create();
    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-06-01',
    ]);

    $earlier = new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '50',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-01-01',
        'until_month' => '2026-03-01',
    ]);

    expect($earlier->until_month?->toDateString())->toBe('2026-03-01')
        ->and($tag->budgetPeriods()->count())->toBe(2);
});

it('opens a period on a category', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->create();

    $period = new CreateBudgetPeriod()->for($team)->targeting(Category::Utilities)->using([
        'amount' => '8000',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-01-01',
    ]);

    expect($period->category)->toBe(Category::Utilities)
        ->and($period->tag_id)->toBeNull()
        ->and($period->team_id)->toBe($team->getKey());
});

it('throws when another period already covers the category months', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->create();
    new CreateBudgetPeriod()->for($team)->targeting(Category::Groceries)->using([
        'amount' => '500',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-01-01',
    ]);

    new CreateBudgetPeriod()->for($team)->targeting(Category::Groceries)->using([
        'amount' => '600',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-05-01',
    ]);
})->throws(Exception::class, 'A budget period cannot overlap another one on the same target and currency.');

it('opens the same category months in another currency', function (): void {
    $team = Team::factory()->create();
    new CreateBudgetPeriod()->for($team)->targeting(Category::Groceries)->using([
        'amount' => '500',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
    ]);

    $other = new CreateBudgetPeriod()->for($team)->targeting(Category::Groceries)->using([
        'amount' => '600',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
    ]);

    expect($team->budgets()->count())->toBe(2)
        ->and($other->category)->toBe(Category::Groceries);
});

it('opens a category period beside a tag period covering the same months', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $currency = Currency::factory()->create();
    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '500',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-01-01',
    ]);

    $category = new CreateBudgetPeriod()->for($tag->team)->targeting(Category::Groceries)->using([
        'amount' => '600',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-01-01',
    ]);

    expect($tag->team->budgets()->count())->toBe(2)
        ->and($category->tag_id)->toBeNull();
});

it('nests a tag period under a category period', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $tag = Tag::factory()->for($team)->create();

    $period = new CreateBudgetPeriod()->for($team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => $parent->currency_id,
        'from_month' => '2026-01-01',
        'parent_id' => $parent->getKey(),
    ]);

    expect($period->parent_id)->toBe($parent->getKey())
        ->and($period->isNested())->toBeTrue()
        ->and($period->scopedCategory())->toBe(Category::Utilities)
        ->and($parent->children()->count())->toBe(1);
});

it('opens a plain tag period when the parent is left blank', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    $period = new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
        'parent_id' => '',
    ]);

    expect($period->parent_id)->toBeNull()
        ->and($period->isNested())->toBeFalse()
        ->and($period->scopedCategory())->toBeNull();
});

it('throws when a category period is nested', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();

    new CreateBudgetPeriod()->for($team)->targeting(Category::Groceries)->using([
        'amount' => '100',
        'currency_id' => $parent->currency_id,
        'from_month' => '2026-01-01',
        'parent_id' => $parent->getKey(),
    ]);
})->throws(Exception::class, 'Only a tag budget can be nested under a category budget.');

it('throws when the parent belongs to another team', function (): void {
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for(Team::factory())->create();
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => $parent->currency_id,
        'from_month' => '2026-01-01',
        'parent_id' => $parent->getKey(),
    ]);
})->throws(Exception::class, 'A budget period cannot be nested under a budget of another team.');

it('throws when the parent is a tag period', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forTag()->for($team)->create();
    $tag = Tag::factory()->for($team)->create();

    new CreateBudgetPeriod()->for($team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => $parent->currency_id,
        'from_month' => '2026-01-01',
        'parent_id' => $parent->getKey(),
    ]);
})->throws(Exception::class, 'A budget period can only be nested under a category budget.');

it('throws when the parent is in another currency', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $tag = Tag::factory()->for($team)->create();

    new CreateBudgetPeriod()->for($team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
        'parent_id' => $parent->getKey(),
    ]);
})->throws(Exception::class, 'A budget period cannot be nested under a budget in another currency.');

it('throws when the parent is not named by its key', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    new CreateBudgetPeriod()->for($tag->team)->targeting($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
        'parent_id' => 12,
    ]);
})->throws(Exception::class, 'A budget period parent must be named by its key.');

it('keeps the parent when a stopped sub-budget is resumed', function (): void {
    $team = Team::factory()->create();
    $parent = BudgetPeriod::factory()->forCategory(Category::Utilities)->for($team)->create();
    $stopped = BudgetPeriod::factory()->within($parent)->paused()->create();

    $resumed = new CreateBudgetPeriod()->for($team)->resume($stopped);

    expect($resumed->parent_id)->toBe($parent->getKey())
        ->and($resumed->tag_id)->toBe($stopped->tag_id);
});
