<?php

namespace App\Modules\Core\Catalog\Enums\Currency\Quote;

enum QuoteSource: string
{
    /**
     * Represent the "live" source, which gets the
     * quote from an API in real-time.
     */
    case Live = 'live';

    /**
     * Represent the "manual" source, which gets the
     * quote from a manual input.
     */
    case Manual = 'manual';

    /**
     * Represent the "scheduled" source, which gets the
     * quote from a scheduled job.
     */
    case Scheduled = 'scheduled';
}
