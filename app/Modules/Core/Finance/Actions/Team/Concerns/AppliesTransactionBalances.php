<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Shared balance arithmetic for the transfer actions. Requires the
 * using class to expose a `?Team $team` property, as
 * \App\Modules\Core\Finance\Actions\Team\CreateTransfer does.
 */
trait AppliesTransactionBalances
{
    /**
     * Apply the effect of the transaction on the balance its account
     * holds for its currency.
     */
    protected function applyBalance(Team $team, Transaction $transaction): void
    {
        $this->moveBalance($team, $transaction, $transaction->type->sign());
    }

    /**
     * Move the balance its account holds for the currency of the
     * transaction by its amount, in the given direction.
     */
    protected function moveBalance(Team $team, Transaction $transaction, string $sign): void
    {
        $team->accounts()
            ->whereKey($transaction->getAttribute($transaction->account()->getForeignKeyName()))
            ->firstOrFail()
            ->currencies()
            ->updateExistingPivot($transaction->getAttribute($transaction->currency()->getForeignKeyName()), [
                'balance' => DB::raw("balance {$sign} {$transaction->amount}"), // @phpstan-ignore-line argument.type Sign and amount come from the transaction.
            ]);
    }

    /**
     * Revert the effect of the transaction on the balance its account
     * holds for its currency.
     */
    protected function revertBalance(Team $team, Transaction $transaction): void
    {
        $this->moveBalance($team, $transaction, $transaction->type->inverseSign());
    }
}
