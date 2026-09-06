<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Subscription\BillingInterval;

/**
 * Unit tests for \App\Modules\Billing\Enums\Subscription\BillingInterval.
 */
it('reads the price id from config', function (): void {
    config()->set('billing.plans.pro.prices.month', 'pri_month');
    config()->set('billing.plans.pro.prices.year', 'pri_year');

    expect(BillingInterval::Month->priceId())->toBe('pri_month')
        ->and(BillingInterval::Year->priceId())->toBe('pri_year');
});

it('resolves the interval whose price id matches', function (): void {
    config()->set('billing.plans.pro.prices.month', 'pri_month');
    config()->set('billing.plans.pro.prices.year', 'pri_year');

    expect(BillingInterval::fromPriceId('pri_month'))->toBe(BillingInterval::Month)
        ->and(BillingInterval::fromPriceId('pri_year'))->toBe(BillingInterval::Year)
        ->and(BillingInterval::fromPriceId('pri_unknown'))->toBeNull();
});
