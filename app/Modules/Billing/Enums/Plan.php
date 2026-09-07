<?php

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
     * Get the features that the plan allows.
     *
     * @return array<int, \App\Modules\Billing\Enums\Feature>
     */
    public function features(): array
    {
        return [
            // This feature indicates if the
            // team is currently able to add accounts.
            Feature::AddAccounts,

            // This feature indicates if the
            // team is currently able to add members.
            Feature::AddMembers,

            // This feature indicates if the
            // team is currently able to add transactions.
            Feature::AddTransactions,
        ];
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
