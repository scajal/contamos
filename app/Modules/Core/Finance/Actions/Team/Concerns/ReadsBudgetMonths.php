<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use Carbon\CarbonImmutable;
use Exception;

/**
 * Reads the month bounds a budget period is written with,
 * whichever notation they arrive in, along with its currency
 * and warning threshold.
 */
trait ReadsBudgetMonths
{
    /**
     * Read one of the bounds as the first day of its month, or
     * null when it was left blank. A value that is not a month
     * at all has escaped validation, so it is refused rather
     * than silently read as today.
     */
    protected function month(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value->startOfMonth();
        }

        if (! is_string($value)) {
            throw new Exception('A budget period month must be a date.');
        }

        return CarbonImmutable::parse($value)->startOfMonth();
    }

    /**
     * Read the key of the currency a budget is expressed in.
     */
    protected function currencyId(mixed $value): string
    {
        if (! is_string($value)) {
            throw new Exception('A budget period must name the currency it is expressed in.');
        }

        return $value;
    }

    /**
     * Read the share of the month's pot at which the team
     * wants to be warned, or null when no warning is
     * wanted. A value that is not a whole number has
     * escaped validation, so it is refused.
     */
    protected function warningThreshold(mixed $value): ?int
    {
        // A blank threshold means no warning at all.
        if (blank($value)) {
            return null;
        }

        // A form submits the figure as a string and an
        // assistant as a number, so both are read alike.
        $threshold = filter_var($value, FILTER_VALIDATE_INT);

        if (! is_int($threshold)) {
            throw new Exception('A budget period warning threshold must be a whole number.');
        }

        return $threshold;
    }
}
