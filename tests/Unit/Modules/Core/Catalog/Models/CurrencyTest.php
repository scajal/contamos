<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Catalog\Models\Currency.
 */
it('uppercases the code when creating a currency', function (): void {
    $currency = Currency::factory()->create(['code' => 'ars']);

    expect($currency->code)->toBe('ARS');
});

it('uppercases the code when updating a currency', function (): void {
    $currency = Currency::factory()->create(['code' => 'ARS']);

    $currency->update(['code' => 'usd']);

    expect($currency->fresh()?->code)->toBe('USD');
});

it('scopes currencies by the is_active string flag', function (): void {
    Currency::factory()->active()->create(['code' => 'ARS']);
    Currency::factory()->inactive()->create(['code' => 'BRL']);

    expect(Currency::query()->isActive('true')->pluck('code')->all())->toBe(['ARS'])
        ->and(Currency::query()->isActive('false')->pluck('code')->all())->toBe(['BRL']);
});

it('returns the latest quote by observed_at as the current quote', function (): void {
    $currency = Currency::factory()->create();
    Quote::factory()->for($currency)->create(['observed_at' => '2024-01-01 00:00:00', 'rate' => '1']);
    $latest = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '2']);

    expect($currency->currentQuote?->is($latest))->toBeTrue()
        ->and($latest->currency->is($currency))->toBeTrue()
        ->and($latest->recorder)->toBeNull();
});

it('exposes the recorder of a quote', function (): void {
    $user = User::factory()->create();
    $quote = Quote::factory()->create(['recorded_by' => $user->getKey()]);

    expect($quote->recorder?->is($user))->toBeTrue();
});
