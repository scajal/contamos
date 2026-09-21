<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\RecurringRule;

use Filament\Support\Contracts\HasLabel;

enum Frequency: string implements HasLabel
{
    /**
     * Recurs every N days.
     */
    case Daily = 'daily';

    /**
     * Recurs every N weeks.
     */
    case Weekly = 'weekly';

    /**
     * Recurs every N months.
     */
    case Monthly = 'monthly';

    /**
     * Recurs every N years.
     */
    case Yearly = 'yearly';

    /**
     * Get the label for the frequency.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => trans('Daily'),
            self::Weekly => trans('Weekly'),
            self::Monthly => trans('Monthly'),
            self::Yearly => trans('Yearly'),
        };
    }
}
