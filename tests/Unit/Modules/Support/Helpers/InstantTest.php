<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Helpers\Instant;

/**
 * Unit tests for \App\Modules\Support\Helpers\Instant.
 */
it('returns no quote when the instant is empty', function (): void {
    $currency = Currency::factory()->fiat()->make();

    expect(Instant::resolveQuote($currency, ''))->toBeNull();
});
