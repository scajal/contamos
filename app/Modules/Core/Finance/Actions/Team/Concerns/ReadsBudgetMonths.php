<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use Carbon\CarbonImmutable;
use Exception;

/**
 * Reads the month bounds a budget period is written with,
 * whichever notation they arrive in.
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
}
