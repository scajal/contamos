<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\RecurringRule;

use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasLabel
{
    /**
     * The rule generates its occurrences on schedule.
     */
    case Active = 'active';

    /**
     * The rule is suspended and generates nothing until
     * it is resumed.
     */
    case Failed = 'failed';

    /**
     * The rule's last run could not generate its occurrence
     * and will be retried.
     */
    case Paused = 'paused';

    /**
     * Get the label for the status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Active => trans('Active'),
            self::Failed => trans('Failed'),
            self::Paused => trans('Paused'),
        };
    }
}
