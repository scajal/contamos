<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Transaction;

enum TransactionType: string
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
