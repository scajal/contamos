<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Enums\Currency\Quote;

use Filament\Support\Contracts\HasLabel;

enum QuoteSource: string implements HasLabel
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

    /**
     * Get the label of the quote source.
     */
    public function getLabel(): string
    {
        return trans("enums.{$this->value}");
    }
}
