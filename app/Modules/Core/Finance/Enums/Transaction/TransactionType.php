<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Transaction;

use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel
{
    /**
     * Represents an expense.
     */
    case Expense = 'expense';

    /**
     * Represents an income.
     */
    case Income = 'income';

    /**
     * Get the label for the transaction type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Expense => trans('Expense'),
            self::Income => trans('Income'),
        };
    }

    /**
     * Get the sign that reverts the effect of the
     * transaction type on a balance.
     */
    public function inverseSign(): string
    {
        return match ($this) {
            self::Expense => '+',
            self::Income => '-',
        };
    }

    /**
     * Get the sign for the transaction
     * type.
     */
    public function sign(): string
    {
        return match ($this) {
            self::Expense => '-',
            self::Income => '+',
        };
    }
}
