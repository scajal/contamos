<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Objects\OpeningBalance;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Shared opening-balance transaction creation for the create and
 * update account actions. Requires the using class to expose a
 * `CreateTransaction $createTransaction` property and a `?User
 * $user` property, as `CreateAccount` and `UpdateAccount` do.
 */
trait CreatesOpeningBalanceTransactions
{
    /**
     * Create an opening balance transaction for the account
     * in the given currency.
     */
    protected function createOpeningBalance(Team $team, Account $account, string $currencyId, OpeningBalance $openingBalance): void
    {
        $createTransaction = $this->createTransaction->for($team);

        if ($this->user instanceof User) {
            $createTransaction = $createTransaction->by($this->user);
        }

        $createTransaction->using([
            new Transaction()->account()->getForeignKeyName() => $account->getKey(),
            'amount' => $openingBalance->amount,
            new Transaction()->currency()->getForeignKeyName() => $currencyId,
            'occurred_at' => $openingBalance->occurredAt,
            'quote' => $openingBalance->quote,
            'type' => TransactionType::OpeningBalance->value,
        ]);
    }
}
