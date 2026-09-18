<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateAccount
{
    /**
     * The team for which the account will be created.
     */
    public ?Team $team = null;

    /**
     * The user creating the account.
     */
    public ?User $user = null;

    /**
     * Initialize the create account action with the given
     * create transaction action.
     */
    public function __construct(
        private readonly CreateTransaction $createTransaction
    ) {}

    /**
     * Set the user creating the account.
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
    protected function createOpeningBalance(Team $team, Account $account, string $currencyId, array $openingBalance): void
    {
        $createTransaction = $this->createTransaction->for($team);

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
     * @param  array<string, array{amount: string, quote: string, occurred_at: string}>  $openingBalances
     */
    public function using(array $attributes, array $currencyIds, array $openingBalances = []): Account
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('An account cannot be created without a team.');
        }

        $team = $this->team;

        return DB::transaction(function () use ($team, $attributes, $currencyIds, $openingBalances): Account {
            // Create the account for the team, excluding
            // the currencies.
            /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
            $account = $team->accounts()->create($attributes);

            // Sync the currencies for the account.
            $account->currencies()->sync($currencyIds);

            // Create one opening balance row per currency.
            foreach ($openingBalances as $currencyId => $openingBalance) {
                $this->createOpeningBalance($team, $account, $currencyId, $openingBalance);
            }

            // Return the account.
            return $account;
        });
    }
}
