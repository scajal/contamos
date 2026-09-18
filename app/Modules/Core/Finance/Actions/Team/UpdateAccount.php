<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateAccount
{
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
     * Create an opening balance transaction for the account
     * in the given currency.
     *
     * @param  array{amount: string, quote: string, occurred_at: string}  $openingBalance
     */
    protected function createOpeningBalance(Account $account, string $currencyId, array $openingBalance): void
    {
        $createTransaction = $this->createTransaction->for($account->team);

        if ($this->user instanceof User) {
            $createTransaction = $createTransaction->by($this->user);
        }

        $createTransaction->using([
            new Transaction()->account()->getForeignKeyName() => $account->getKey(),
            'amount' => $openingBalance['amount'],
            new Transaction()->currency()->getForeignKeyName() => $currencyId,
            'occurred_at' => $openingBalance['occurred_at'],
            'quote' => $openingBalance['quote'],
            'type' => TransactionType::OpeningBalance->value,
        ]);
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
     * @param  array<string, array{amount: string, quote: string, occurred_at: string}>  $openingBalances
     */
    public function using(array $attributes, array $currencyIds, array $openingBalances = []): Account
    {
        // If the account is not set, throw an exception.
        if (! $this->account instanceof Account) {
            throw new Exception('An account was not set for the update.');
        }

        $account = $this->account;

        return DB::transaction(function () use ($account, $attributes, $currencyIds, $openingBalances): Account {
            // Update the account for the team, excluding
            // the currencies.
            $account->update($attributes);

            // Sync the currencies for the account.
            $account->currencies()->sync($currencyIds);

            // Create one opening balance row per newly
            // attached currency.
            foreach ($openingBalances as $currencyId => $openingBalance) {
                $this->createOpeningBalance($account, $currencyId, $openingBalance);
            }

            // Return the account.
            return $account;
        });
    }
}
