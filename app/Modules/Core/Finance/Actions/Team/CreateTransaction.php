<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
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
        if (! $this->team) {
            throw new Exception('A transaction cannot be created without a team.');
        }

        return DB::transaction(function () use ($attributes): Transaction {
            // Get the selected currency.
            $currency = Currency::query()
                ->find($attributes[new Transaction()->currency()->getForeignKeyName()]);

            // Update the amount to a minor unit before
            // saving it.
            $attributes['amount'] = (int) round(
                (float) $attributes['amount'] * (10 ** $currency->decimal_places)
            );

            // Create the account for the team, excluding
            // the currencies.I
            $transaction = $this->team->transactions()->create($attributes);

            // If the user is provided, associate it with the transaction.
            if ($this->user) {
                $transaction->user()->associate($this->user)->save();
            }

            // Return the transaction.
            return $transaction;
        });
    }
}
