<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\BudgetPeriod;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a budget period sits relative to the current month.
 * The status is derived from the period's own months and
 * from whether another period took over after it, so
 * nothing is stored for it.
 */
enum BudgetPeriodStatus: string implements HasLabel
{
    /**
     * Represents a period whose months are over and which
     * nothing followed, so the tag has no budget from now
     * on until it is resumed.
     */
    case Paused = 'paused';

    /**
     * Represents a period that covers the current month.
     */
    case Running = 'running';

    /**
     * Represents a period that starts in a later month.
     */
    case Scheduled = 'scheduled';

    /**
     * Represents a period whose months are over because it
     * was a single-month objective or because another
     * period took over after it.
     */
    case Ended = 'ended';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Paused => trans('Paused'),
            self::Running => trans('Running'),
            self::Scheduled => trans('Scheduled'),
            self::Ended => trans('Ended'),
        };
    }
}
