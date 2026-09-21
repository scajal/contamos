<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\CreatesOpeningBalanceTransactions;
use App\Modules\Core\Finance\Actions\Team\Concerns\SyncsAccountCurrencies;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateAccount
{
    use CreatesOpeningBalanceTransactions;
    use SyncsAccountCurrencies;

    /**
     * The account for which the update will be performed.
     */
    public ?Account $account = null;

    /**
     * The user updating the account.
     */
    public ?User $user = null;

    /**
     * Initialize the update account action with the given
     * create transaction action.
     */
    public function __construct(
        private readonly CreateTransaction $createTransaction
    ) {}

    /**
     * Set the user updating the account.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

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
     * @param  array<string, \App\Modules\Core\Finance\Objects\OpeningBalance>  $openingBalances
     */
    public function using(array $attributes, array $currencyIds, array $openingBalances = []): Account
    {
        // If the account is not set, throw an exception.
        if (! $this->account instanceof Account) {
            throw new Exception('An account was not set for the update.');
        }

        $account = $this->account;

        // Everything the update writes reads as one entry in
        // the team's activity log.
        return TeamActivity::within(fn (): Account => DB::transaction(function () use ($account, $attributes, $currencyIds, $openingBalances): Account {
            // Update the account for the team, excluding
            // the currencies.
            $account->update($attributes);

            // Sync the currencies for the account.
            $this->syncCurrencies($account, $currencyIds);

            // Create one opening balance row per newly
            // attached currency.
            foreach ($openingBalances as $currencyId => $openingBalance) {
                $this->createOpeningBalance($account->team, $account, $currencyId, $openingBalance);
            }

            // Return the account.
            return $account;
        }));
    }
}
