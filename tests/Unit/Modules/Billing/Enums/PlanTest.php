<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Plan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Unit tests for \App\Modules\Billing\Enums\Plan.
 */
it('labels each plan', function (Plan $plan, string $label): void {
    expect($plan->getLabel())->toBe($label);
})->with([
    'free' => [Plan::Free, 'Free'],
    'pro' => [Plan::Pro, 'Pro'],
]);

it('returns no price for the free plan', function (): void {
    expect(Plan::Free->price(BillingCycle::Monthly))->toBe([
        'formatted' => null,
        'amount' => null,
        'currency' => null,
    ]);
});

it('returns no price when paddle previews nothing', function (): void {
    Cache::flush();
    config()->set('cashier.api_key', 'test_key');
    Http::fake([
        '*/pricing-preview' => Http::response(['data' => ['details' => ['line_items' => []]]]),
    ]);

    expect(Plan::Pro->price(BillingCycle::Monthly))->toBe([
        'formatted' => null,
        'amount' => null,
        'currency' => null,
    ]);
});
