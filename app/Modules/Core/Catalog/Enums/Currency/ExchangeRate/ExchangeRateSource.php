<?php

namespace App\Modules\Core\Catalog\Enums\Currency\ExchangeRate;

enum ExchangeRateSource: string
{
    /**
     * Represent the "live" source, which gets the
     * rate from an API in real-time.
     */
    case Live = 'live';

    /**
     * Represent the "manual" source, which gets the
     * rate from a manual input.
     */
    case Manual = 'manual';

    /**
     * Represent the "scheduled" source, which gets the
     * rate from a scheduled job.
     */
    case Scheduled = 'scheduled';
}
