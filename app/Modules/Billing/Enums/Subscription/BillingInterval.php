<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums\Subscription;

enum BillingInterval: string
{
    /**
     * Billed monthly.
     */
    case Month = 'month';

    /**
     * Billed yearly.
     */
    case Year = 'year';

    /**
     * Resolve the interval whose price id matches the
     * given Paddle price id.
     */
    public static function fromPriceId(string $priceId): ?self
    {
        foreach (self::cases() as $interval) {
            if ($interval->priceId() === $priceId) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * Get the Paddle price id for the Pro plan at
     * this interval.
     */
    public function priceId(): string
    {
        $priceId = config("billing.plans.pro.prices.{$this->value}");

        return is_string($priceId) ? $priceId : '';
    }
}
