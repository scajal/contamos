<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum Plan: string implements HasLabel
{
    /**
     * The free plan.
     */
    case Free = 'free';

    /**
     * The paid plan.
     */
    case Pro = 'pro';

    /**
     * Determine if the plan allows the given usage
     * for a feature.
     */
    public function allows(string $feature, int $current): bool
    {
        return ($limit = $this->limit($feature)) === null
            || $current < $limit;
    }

    /**
     * Get the human-readable label for the
     * plan.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }

    /**
     * Get the plan's limit for the given feature,
     * or null when unlimited.
     */
    public function limit(string $feature): ?int
    {
        return is_int($limit = config("billing.limits.{$this->value}.{$feature}"))
            ? $limit
            : null;
    }
}
