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

it('reads a comma as the decimal separator on a Spanish locale', function (): void {
    expect(new Amount()->normalize('350,50', ['locale' => 'es']))->toBe('350.50');
});

it('drops the grouping separator of the locale', function (): void {
    expect(new Amount()->normalize('1.234,50', ['locale' => 'es']))->toBe('1234.50');
    expect(new Amount()->normalize('1,234.50', ['locale' => 'en']))->toBe('1234.50');
});

it('reads a lone comma as a decimal point on a locale that groups with it', function (): void {
    expect(new Amount()->normalize('350,50', ['locale' => 'en']))->toBe('350.50');
});

it('reads a lone comma before three digits as grouping only where the locale groups with it', function (): void {
    expect(new Amount()->normalize('1,234', ['locale' => 'en']))->toBe('1234');
    expect(new Amount()->normalize('1,234', ['locale' => 'es']))->toBe('1.234');
});

it('reads a repeated separator as grouping', function (): void {
    expect(new Amount()->normalize('1.234.567', ['locale' => 'es']))->toBe('1234567');
});

it('leaves a dot-decimal amount untouched', function (): void {
    expect(new Amount()->normalize('350.50', ['locale' => 'es']))->toBe('350.50');
    expect(new Amount()->normalize('1234', ['locale' => 'es']))->toBe('1234');
});

it('keeps the crypto scale of a comma decimal', function (): void {
    expect(new Amount()->normalize('0,123456789', ['locale' => 'es']))->toBe('0.123456789');
});

it('leaves values that carry no separator alone', function (): void {
    expect(new Amount()->normalize(null))->toBeNull();
    expect(new Amount()->normalize(12.5))->toBe(12.5);
    expect(new Amount()->normalize(''))->toBe('');
    expect(new Amount()->normalize('  350,50  ', ['locale' => 'es']))->toBe('350.50');
});
