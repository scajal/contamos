<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod.
 */
it('throws when a tag was not provided', function (): void {
    (new CreateBudgetPeriod)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period cannot be created without a tag.');

it('throws when the first month is missing', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => null,
    ]);
})->throws(Exception::class, 'A budget period cannot be created without a first month.');

it('throws when a month is not a date', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => 2026,
    ]);
})->throws(Exception::class, 'A budget period month must be a date.');

it('throws when the currency is not named', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '100',
        'currency_id' => 1,
        'from_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period must name the currency it is expressed in.');

it('throws when the stretch ends before it starts', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();

    (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '100',
        'currency_id' => Currency::factory()->create()->getKey(),
        'from_month' => '2026-06-01',
        'until_month' => '2026-01-01',
    ]);
})->throws(Exception::class, 'A budget period cannot end before it starts.');

it('opens a period from an immutable month', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $currency = Currency::factory()->create();

    $period = (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'from_month' => CarbonImmutable::parse('2026-01-15'),
    ]);

    expect($period->from_month?->toDateString())->toBe('2026-01-01')
        ->and($period->until_month)->toBeNull();
});

it('opens a stretch that closes before a later period starts', function (): void {
    $tag = Tag::factory()->for(Team::factory())->create();
    $currency = Currency::factory()->create();
    (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-06-01',
    ]);

    $earlier = (new CreateBudgetPeriod)->for($tag)->using([
        'amount' => '50',
        'currency_id' => $currency->getKey(),
        'from_month' => '2026-01-01',
        'until_month' => '2026-03-01',
    ]);

    expect($earlier->until_month?->toDateString())->toBe('2026-03-01')
        ->and($tag->budgetPeriods()->count())->toBe(2);
});
