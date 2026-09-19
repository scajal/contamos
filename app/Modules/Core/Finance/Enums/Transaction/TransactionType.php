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
     * Represents the balance an account started with.
     */
    case OpeningBalance = 'opening_balance';

    /**
     * Represents the money arriving at the destination
     * account of a transfer.
     */
    case TransferIn = 'transfer_in';

    /**
     * Represents the money leaving the origin
     * account of a transfer.
     */
    case TransferOut = 'transfer_out';

    /**
     * Get the label for the transaction type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Expense => trans('Expense'),
            self::Income => trans('Income'),
            self::OpeningBalance => trans('Opening balance'),
            self::TransferIn => trans('Transfer in'),
            self::TransferOut => trans('Transfer out'),
        };
    }

    /**
     * Get the types a team records as a plain transaction, which is
     * what the transaction create route and request accept.
     *
     * @return array<int, self>
     */
    public static function recordableCases(): array
    {
        return [self::Income, self::Expense];
    }

    /**
     * Get the types that are a leg of a transfer.
     *
     * @return array<int, self>
     */
    public static function transferCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->isTransfer()));
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
            self::OpeningBalance => '-',
            self::TransferIn => '-',
            self::TransferOut => '+',
        };
    }

    /**
     * Determine whether the transaction type is
     * one of the legs of a transfer.
     */
    public function isTransfer(): bool
    {
        return in_array($this, [self::TransferIn, self::TransferOut], true);
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
            self::OpeningBalance => '+',
            self::TransferIn => '+',
            self::TransferOut => '-',
        };
    }
}
