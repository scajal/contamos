<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateTransaction
{
    /**
     * The team for which the transaction will be created.
     */
    public ?Team $team = null;

    /**
     * The user that made the transaction.
     */
    public ?User $user = null;

    /**
     * Set the user that made the transaction.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team for which the transaction will be created.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Create a new transaction for a team with the provided
     * information.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(array $attributes): Transaction
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof \App\Modules\Core\Identity\Models\Team) {
            throw new Exception('A transaction cannot be created without a team.');
        }

        return DB::transaction(function () use ($attributes): Transaction {
            // Get the currency for the transaction.
            /** @var \App\Modules\Core\Catalog\Models\Currency $currency */
            $currency = Currency::query()
                ->whereKey($attributes['currency_id'])
                ->first();

            // Create the account for the team.
            /** @var \App\Modules\Core\Finance\Models\Team\Transaction $transaction */
            $transaction = $this->team?->transactions()->create([
                ...$attributes,
                // Set the decimal places for the transaction.
                'decimal_places' => $currency->decimal_places,
            ]);

            // If the user is provided, associate it with the transaction.
            if ($this->user instanceof \App\Modules\Core\Identity\Models\User) {
                $transaction->user()->associate($this->user)->save();
            }

            // Update the account balance.
            $this->team?->accounts()
                ->whereKey($transaction->account->getKey())
                ->firstOrFail()
                ->currencies()
                ->updateExistingPivot($currency->getKey(), [
                    'balance' => DB::raw("balance {$transaction->type->sign()} {$transaction->amount}"),
                ]);

            // Return the transaction.
            return $transaction;
        });
    }
}
