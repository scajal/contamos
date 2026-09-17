<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingCycle;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Billing\Enums\BillingCycle.
 */
it('translates the billing cycle label', function (BillingCycle $cycle, string $label): void {
    App::setLocale('en');

    expect($cycle->getLabel())->toBe($label);
})->with([
    'monthly' => [BillingCycle::Monthly, 'Monthly'],
    'yearly' => [BillingCycle::Yearly, 'Yearly'],
]);
