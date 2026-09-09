<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Cache;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\PricePreview;

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
            ->mapWithKeys(fn (Feature $feature) => [$feature->value => $feature->limitOn($this)])
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
     * Preview the plan's price for the billing cycle through
     * Paddle, cached for a while since prices rarely change.
     * Paddle is the only place the amounts live, so they are
     * fetched rather than duplicated here. A failed preview
     * yields nulls and is not cached in place of a real price.
     *
     * @return array{formatted: string|null, amount: int|null}
     */
    public function price(BillingCycle $cycle): array
    {
        // The free plan has no price to preview.
        if ($this === self::Free) {
            return ['formatted' => null, 'amount' => null];
        }

        /** @var array{formatted: string|null, amount: int|null} $price */
        $price = rescue(fn (): array => Cache::remember(
            "billing.plans.{$this->value}.{$cycle->value}.price",
            CarbonImmutable::now()->addHours(12),
            function () use ($cycle): array {
                if (! ($preview = Cashier::previewPrices([$this->priceId($cycle)])->first()) instanceof PricePreview) {
                    throw new \RuntimeException('Paddle returned no price preview.');
                }

                // The total in minor units lets the page work out the
                // yearly saving without parsing formatted money.
                $total = $preview->rawTotal();

                return [
                    'formatted' => $preview->total(),
                    'amount' => is_numeric($total) ? (int) $total : null,
                ];
            },
        ), ['formatted' => null, 'amount' => null]);

        return $price;
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
