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
     * Infer the transaction type based on the
     * provided amount.
     */
    public static function infer(int $amount): self
    {
        return $amount > 0
            ? self::Income
            : self::Expense;
    }
}
