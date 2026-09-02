<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateAccount
{
    /**
     * The account for which the update will be performed.
     */
    public ?Account $account = null;

    /**
     * Set the account for which the update will be performed.
     */
    public function for(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Update an existing account for a team with the provided
     * information.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string>  $currencyIds
     */
    public function using(array $attributes, array $currencyIds): Account
    {
        // If the account is not set, throw an exception.
        if (! $this->account) {
            throw new Exception('An account was not set for the update.');
        }

        return DB::transaction(function () use ($attributes, $currencyIds): Account {
            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = $this->account;

            // Create the account for the team, excluding
            // the currencies.I
            $account->update($attributes);

            // Sync the currencies for the account.
            $account->currencies()->sync($currencyIds);

            // Return the account.
            return $account;
        });
    }
}
