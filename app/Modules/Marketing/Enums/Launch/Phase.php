<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Enums\Launch;

use Filament\Support\Contracts\HasLabel;

/**
 * Where the Product Hunt launch stands, which decides what
 * the homepage says about it.
 */
enum Phase: string implements HasLabel
{
    /**
     * Scheduled but not live yet.
     */
    case Upcoming = 'upcoming';

    /**
     * Live on Product Hunt today.
     */
    case Live = 'live';

    /**
     * Over on Product Hunt, with its discount code still valid.
     */
    case Offer = 'offer';

    /**
     * Get the label for the phase.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Upcoming => trans('Upcoming'),
            self::Live => trans('Live'),
            self::Offer => trans('Offer'),
        };
    }
}
