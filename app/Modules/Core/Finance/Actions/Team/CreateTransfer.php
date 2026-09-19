<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\Concerns\AppliesTransactionBalances;
use App\Modules\Core\Finance\Actions\Team\Concerns\BuildsTransferTransactions;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateTransfer
{
    use AppliesTransactionBalances;
    use BuildsTransferTransactions;

    /**
     * The team for which the transfer will be created.
     */
    public ?Team $team = null;

    /**
     * The user that made the transfer.
     */
    public ?User $user = null;

    /**
     * Set the user that made the transfer.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team for which the transfer will be created.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Create a new transfer for a team, along with the transactions
     * it owns, and apply their effect on the account balances.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $tagIds
     */
    public function using(array $attributes, array $tagIds = []): Transfer
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A transfer cannot be created without a team.');
        }

        $team = $this->team;

        return DB::transaction(function () use ($team, $attributes, $tagIds): Transfer {
            // Resolve both sides of the transfer, failing when an account
            // belongs to another team.
            $originAccount = $this->resolveAccount($team, $attributes['origin_account_id'] ?? null);
            $destinationAccount = $this->resolveAccount($team, $attributes['destination_account_id'] ?? null);
            $originCurrency = $this->resolveCurrency($attributes['origin_currency_id'] ?? null);
            $destinationCurrency = $this->resolveCurrency($attributes['destination_currency_id'] ?? null);

            // Create the transfer for the team.
            /** @var \App\Modules\Core\Finance\Models\Team\Transfer $transfer */
            $transfer = $team->transfers()->create($this->transferAttributes($attributes));

            // If the user is provided, associate it with the transfer.
            if ($this->user instanceof User) {
                $transfer->user()->associate($this->user)->save();
            }

            // Create the leg leaving the origin account and the leg
            // arriving at the destination account.
            $transactions = [
                $this->createTransaction($transfer, $originAccount, TransactionType::TransferOut, $originCurrency, $attributes['amount_sent'] ?? null, $attributes['origin_quote'] ?? null, $transfer->description),
                $this->createTransaction($transfer, $destinationAccount, TransactionType::TransferIn, $destinationCurrency, $attributes['amount_received'] ?? null, $attributes['destination_quote'] ?? null, $transfer->description),
            ];

            // Create the fee charged on the origin account.
            if (filled($attributes['origin_fee'] ?? null)) {
                $transactions[] = $this->createTransaction($transfer, $originAccount, TransactionType::Expense, $originCurrency, $attributes['origin_fee'], $attributes['origin_quote'] ?? null, trans('Transfer fee'));
            }

            // Create the fee charged on the destination account.
            if (filled($attributes['destination_fee'] ?? null)) {
                $transactions[] = $this->createTransaction($transfer, $destinationAccount, TransactionType::Expense, $destinationCurrency, $attributes['destination_fee'], $attributes['destination_quote'] ?? null, trans('Transfer fee'));
            }

            // Attach the tags to the transfer.
            $transfer->attachTags($tagIds);

            foreach ($transactions as $transaction) {
                // Attach the tags to the owned transaction.
                $transaction->attachTags($tagIds);

                // Apply its effect on the account balance.
                $this->applyBalance($team, $transaction);
            }

            // Return the transfer.
            return $transfer;
        });
    }

    /**
     * Create one of the transactions owned by
     * the transfer.
     */
    protected function createTransaction(Transfer $transfer, Account $account, TransactionType $type, Currency $currency, mixed $amount, mixed $quote, ?string $description): Transaction
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Transaction $transaction */
        $transaction = $account->transactions()->create(
            $this->transactionAttributes($transfer, $account, $currency, $type, $amount, $quote, $description)
        );

        return $transaction;
    }
}
