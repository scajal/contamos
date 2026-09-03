<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateAccount
{
    /**
     * The team for which the account will be created.
     */
    public ?Team $team = null;

    /**
     * Set the team for which the account will be created.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Create a new account for a team with the provided
     * information.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string>  $currencyIds
     */
    public function using(array $attributes, array $currencyIds): Account
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof \App\Modules\Core\Identity\Models\Team) {
            throw new Exception('An account cannot be created without a team.');
        }

        return DB::transaction(function () use ($attributes, $currencyIds): Account {
            // Create the account for the team, excluding
            // the currencies.I
            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = $this->team?->accounts()->create($attributes);

            // Sync the currencies for the account.
            $account->currencies()->sync($currencyIds);

            // Return the account.
            return $account;
        });
    }
}
