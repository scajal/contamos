<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\UpdateTag;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateTag.
 */
it('throws when a team was not provided', function (): void {
    (new UpdateTag)->using(Tag::factory()->create(), ['name' => 'Comida']);
})->throws(Exception::class, 'A tag cannot be updated without a team.');

it('renames the tag', function (): void {
    $team = Team::factory()->create();
    $tag = Tag::factory()->for($team)->create(['name' => 'Old']);

    $updated = (new UpdateTag)->for($team)->using($tag, ['name' => 'Comida']);

    expect($updated->name)->toBe('Comida');
});

it('drops a running stretch that only ever covered this month when the amount is cleared', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();
    $tag = Tag::factory()->for($team)->create();
    $period = $tag->budgetPeriods()->create([
        'amount' => '100.00',
        'currency_id' => $currency->getKey(),
        'from_month' => now()->startOfMonth(),
        'until_month' => null,
    ]);

    (new UpdateTag)->for($team)->using($tag, ['name' => $tag->name], [
        $currency->getKey() => null,
    ]);

    expect(BudgetPeriod::query()->find($period->getKey()))->toBeNull();
});

it('leaves a running budget untouched when the amount has not moved', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();
    $tag = Tag::factory()->for($team)->create();
    $period = $tag->budgetPeriods()->create([
        'amount' => '100.00',
        'currency_id' => $currency->getKey(),
        'from_month' => now()->startOfMonth(),
        'until_month' => null,
    ]);

    (new UpdateTag)->for($team)->using($tag, ['name' => $tag->name], [
        $currency->getKey() => '100.00',
    ]);

    expect($period->fresh()?->amount)->toBe('100.000000000000000000')
        ->and($tag->budgetPeriods()->count())->toBe(1);
});
