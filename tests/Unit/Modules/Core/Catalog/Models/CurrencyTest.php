<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;

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

it('returns the latest quote observed at or before now as the current quote', function (): void {
    $this->travelTo('2024-06-15 12:00:00');

    $currency = Currency::factory()->create();
    Quote::factory()->for($currency)->create(['observed_at' => '2024-01-01 00:00:00', 'rate' => '1']);
    $current = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-15 12:00:00', 'rate' => '2']);
    Quote::factory()->for($currency)->create(['observed_at' => '2024-07-01 00:00:00', 'rate' => '3']);

    expect($currency->currentQuote?->is($current))->toBeTrue()
        ->and($current->currency->is($currency))->toBeTrue()
        ->and($current->recorder)->toBeNull();
});

it('returns no current quote when every quote is in the future', function (): void {
    $this->travelTo('2024-06-15 12:00:00');

    $currency = Currency::factory()->create();
    Quote::factory()->for($currency)->create(['observed_at' => '2024-07-01 00:00:00', 'rate' => '3']);

    expect($currency->currentQuote)->toBeNull();
});

it('returns the latest quote observed at or before the given instant', function (): void {
    $currency = Currency::factory()->create();
    Quote::factory()->for($currency)->create(['observed_at' => '2024-01-01 00:00:00', 'rate' => '1']);
    $asOf = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);
    Quote::factory()->for($currency)->create(['observed_at' => '2024-06-15 00:00:00', 'rate' => '2']);

    expect($currency->quoteAsOf(CarbonImmutable::parse('2024-06-01 12:00:00'))?->is($asOf))->toBeTrue();
});

it('returns no quote as of an instant before every observation', function (): void {
    $currency = Currency::factory()->create();
    Quote::factory()->for($currency)->create(['observed_at' => '2024-06-15 00:00:00', 'rate' => '2']);

    expect($currency->quoteAsOf(CarbonImmutable::parse('2024-01-01 00:00:00')))->toBeNull();
});

it('uses the previous observation when no quote exists on that day', function (): void {
    $currency = Currency::factory()->create();
    $friday = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-14 00:00:00', 'rate' => '40']);

    expect($currency->quoteAsOf(CarbonImmutable::parse('2024-06-15 12:00:00'))?->is($friday))->toBeTrue();
});

it('exposes the recorder of a quote', function (): void {
    $user = User::factory()->create();
    $quote = Quote::factory()->create(['recorded_by' => $user->getKey()]);

    expect($quote->recorder?->is($user))->toBeTrue();
});
