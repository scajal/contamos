<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum Plan: string
{
    /**
     * Represents the free plan.
     */
    case Free = 'free';

    /**
     * Represents the pro plan.
     */
    case Pro = 'pro';

    /**
     * Get the limits for the plan instance.
     *
     * @return array{
     *      accounts: int|null,
     *      members: int|null,
     *      transactionsPerMonth: int|null,
     * }
     */
    public function limits(): array
    {
        return match ($this) {
            self::Free => config()->array("billing.plans.{$this->value}.limits"),
            self::Pro => config()->array("billing.plans.{$this->value}.limits"),
        };
    }

    /**
     * Get the price IDs for the plan
     * instance.
     *
     * @return array{
     *      monthly: string,
     *      yearly: string,
     * }|null
     */
    public function prices(): ?array
    {
        return match ($this) {
            self::Pro => config()->array("billing.plans.{$this->value}.prices"),
            default => null,
        };
    }

    /**
     * Get the product ID for the plan instance.
     */
    public function productId(): ?string
    {
        return match ($this) {
            self::Pro => config()->string("billing.plans.{$this->value}.productId"),
            default => null,
        };
    }
}
