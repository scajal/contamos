<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Helpers\Amount;

/**
 * Unit tests for \App\Modules\Support\Helpers\Amount.
 */
it('formats a zero-decimal amount without a fraction', function (): void {
    $currency = Currency::factory()->fiat()->make([
        'decimal_places' => 0,
        'symbol' => '$',
    ]);

    expect(new Amount()->format('12', $currency, ['locale' => 'en']))->toBe('$ 12');
});

it('treats a blank integer part as zero after stripping leading zeros', function (): void {
    $currency = Currency::factory()->fiat()->make([
        'decimal_places' => 2,
        'symbol' => '$',
    ]);

    expect(new Amount()->format('000.50', $currency, ['locale' => 'en']))->toBe('$ 0.50');
});

it('falls back to groups of three when the locale has no grouping size', function (): void {
    $currency = Currency::factory()->fiat()->make([
        'decimal_places' => 0,
        'symbol' => '$',
    ]);

    expect(new Amount()->format('1234', $currency, ['locale' => 'en_US_POSIX']))->toBe('$ 1,234');
});
