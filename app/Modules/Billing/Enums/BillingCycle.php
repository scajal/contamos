<?php

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasLabel
{
    /**
     * Represents the monthly billing cycle.
     */
    case Monthly = 'monthly';

    /**
     * Represents the yearly billing cycle.
     */
    case Yearly = 'yearly';

    /**
     * Get the label for the billing cycle.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => trans('Monthly'),
            self::Yearly => trans('Yearly'),
        };
    }
}
