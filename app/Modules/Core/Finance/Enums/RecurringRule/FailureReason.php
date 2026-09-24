<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\RecurringRule;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a recurring rule could not generate its occurrence.
 *
 * @see \App\Modules\Core\Finance\Services\RecurringRule\Actions\CreateDueTransactions
 */
enum FailureReason: string implements HasLabel
{
    /**
     * The rule's account is inactive.
     */
    case InactiveAccount = 'inactive_account';

    /**
     * The rule's currency is no longer on its account.
     */
    case MissingCurrency = 'missing_currency';

    /**
     * The rule's currency has no rate for the due date.
     */
    case MissingRate = 'missing_rate';

    /**
     * Get the sentence that explains the failure.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::InactiveAccount => trans('The account is inactive.'),
            self::MissingCurrency => trans('The currency is no longer on the account.'),
            self::MissingRate => trans('There is no rate for the due date.'),
        };
    }
}
