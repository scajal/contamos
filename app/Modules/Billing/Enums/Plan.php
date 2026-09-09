<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum Plan: string implements HasLabel
{
    /**
     * Represents the free plan (default).
     */
    case Free = 'free';

    /**
     * Represents the pro plan.
     */
    case Pro = 'pro';

    /**
     * Get the features that the plan allows, keyed by
     * feature value, with the plan's limit for each. A
     * null limit means unlimited.
     *
     * @return array<string, int|null>
     */
    public function features(): array
    {
        return collect(Feature::cases())
            ->mapWithKeys(fn (Feature $feature): array => [$feature->value => $feature->limitOn($this)])
            ->toArray();
    }

    /**
     * Get the label for the plan.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }

    /**
     * Get the price ID for the provided
     * billing cycle.
     */
    public function priceId(BillingCycle $cycle): string
    {
        return match ($this) {
            // The free plan does not have a price ID.
            self::Free => '',

            // The pro plan has a price ID for the
            // provided billing cycle.
            self::Pro => match ($cycle) {
                BillingCycle::Monthly => config()->string('billing.plans.pro.monthly_price'),
                BillingCycle::Yearly => config()->string('billing.plans.pro.yearly_price'),
            }
        };
    }

    /**
     * Get the product ID for the plan.
     */
    public function productId(): string
    {
        return match ($this) {
            // The free plan does not have a product ID.
            self::Free => '',

            // The pro plan has a product ID.
            self::Pro => config()->string('billing.plans.pro.id'),
        };
    }
}
