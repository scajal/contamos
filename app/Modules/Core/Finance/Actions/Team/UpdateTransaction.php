<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateTransaction
{
    /**
     * The team that owns the transaction to update.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the transaction to update.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Update the transaction, replace its tags, and keep the
     * affected account balances correct for whichever fields
     * changed.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $tagIds
     */
    public function using(Transaction $transaction, array $attributes, array $tagIds = []): Transaction
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof \App\Modules\Core\Identity\Models\Team) {
            throw new Exception('A transaction cannot be updated without a team.');
        }

        return DB::transaction(function () use ($transaction, $attributes, $tagIds): Transaction {
            // Revert the current effect on the current account balance
            // for the current currency.
            $this->team
                ?->accounts()
                ->whereKey($transaction->{$transaction->account()->getForeignKeyName()})
                ->firstOrFail()
                ->currencies()
                ->updateExistingPivot($transaction->{$transaction->currency()->getForeignKeyName()}, [
                    'balance' => DB::raw("balance {$transaction->type->inverseSign()} {$transaction->amount}"), // @phpstan-ignore-line argument.type Sign and amount come from the transaction.
                ]);

            // Get the new currency for the transaction.
            /** @var \App\Modules\Core\Catalog\Models\Currency $currency */
            $currency = Currency::query()
                ->whereKey($attributes['currency_id'])
                ->first();

            // Fill the new attributes and save the transaction.
            $transaction->fill([
                ...$attributes,
                'decimal_places' => $currency->decimal_places,
            ])->save();

            // Replace the tag set with the submitted ids.
            $transaction->syncTags($tagIds);

            // Apply the new effect on the new account balance for the
            // new currency.
            $this->team
                ?->accounts()
                ->whereKey($transaction->{$transaction->account()->getForeignKeyName()})
                ->firstOrFail()
                ->currencies()
                ->updateExistingPivot($currency->getKey(), [
                    'balance' => DB::raw("balance {$transaction->type->sign()} {$transaction->amount}"), // @phpstan-ignore-line argument.type Sign and amount come from the transaction.
                ]);

            // Return the updated transaction.
            return $transaction;
        });
    }
}
