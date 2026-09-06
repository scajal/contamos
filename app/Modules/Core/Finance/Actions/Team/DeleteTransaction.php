<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteTransaction
{
    /**
     * The team that owns the transaction to delete.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the transaction to delete.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Delete the transaction and revert its effect on the
     * account balance for its currency.
     */
    public function using(Transaction $transaction): void
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof \App\Modules\Core\Identity\Models\Team) {
            throw new Exception('A transaction cannot be deleted without a team.');
        }

        DB::transaction(function () use ($transaction): void {
            // Revert the account balance for the transaction currency.
            $this->team
                ?->accounts()
                ->whereKey($transaction->{$transaction->account()->getForeignKeyName()})
                ->firstOrFail()
                ->currencies()
                ->updateExistingPivot($transaction->{$transaction->currency()->getForeignKeyName()}, [
                    'balance' => DB::raw("balance {$transaction->type->inverseSign()} {$transaction->amount}"), // @phpstan-ignore-line argument.type Sign and amount come from the transaction.
                ]);

            // Delete the transaction.
            $transaction->delete();
        });
    }
}
