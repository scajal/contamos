<?php

declare(strict_types=1);

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
    case ManualOrScheduled = 'manual/scheduled';
}
