<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateAccount
{
    /**
     * Update an existing account for a team with the provided
     * information.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string>  $currencyIds
     */
    public function using(Account $account, array $attributes, array $currencyIds): Account
    {
        return DB::transaction(function () use ($account, $attributes, $currencyIds): Account {
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
