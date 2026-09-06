<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;

/**
 * Unit tests for \App\Modules\Billing\Enums\Plan.
 */
it('reads limits from config', function (): void {
    config()->set('billing.limits.free.accounts', 3);
    config()->set('billing.limits.pro.accounts', null);

    expect(Plan::Free->limit('accounts'))->toBe(3)
        ->and(Plan::Pro->limit('accounts'))->toBeNull();
});

it('returns null for an unconfigured feature', function (): void {
    expect(Plan::Free->limit('unknown'))->toBeNull();
});

it('allows usage under the limit and rejects usage at or over it', function (): void {
    config()->set('billing.limits.free.accounts', 3);

    expect(Plan::Free->allows('accounts', 2))->toBeTrue()
        ->and(Plan::Free->allows('accounts', 3))->toBeFalse()
        ->and(Plan::Free->allows('accounts', 4))->toBeFalse();
});

it('always allows usage on an unlimited feature', function (): void {
    config()->set('billing.limits.pro.accounts', null);

    expect(Plan::Pro->allows('accounts', 1000000))->toBeTrue();
});
