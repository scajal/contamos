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
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateTransfer
{
    use AppliesTransactionBalances;
    use BuildsTransferTransactions;

    /**
     * The team that owns the transfer to update.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the transfer to update.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Update the transfer and the transactions it owns, replace their
     * tags, and keep the affected account balances correct for
     * whichever fields changed.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $tagIds
     */
    public function using(Transfer $transfer, array $attributes, array $tagIds = []): Transfer
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A transfer cannot be updated without a team.');
        }

        $team = $this->team;

        // Everything the action writes reads as one entry in
        // the team's activity log.
        return TeamActivity::within(fn (): Transfer => DB::transaction(function () use ($team, $transfer, $attributes, $tagIds): Transfer {
            // Load the transactions the transfer currently owns.
            $transfer->load('transactions');

            // Revert the current effect of every owned transaction on
            // the account balances.
            foreach ($transfer->transactions as $transaction) {
                $this->revertBalance($team, $transaction);
            }

            // Resolve the current legs and fees before any of them
            // moves to another account.
            $originLeg = $transfer->originLeg();
            $destinationLeg = $transfer->destinationLeg();
            $originFee = $transfer->originFee();
            $destinationFee = $transfer->destinationFee();

            // Resolve both sides of the transfer, failing when an account
            // belongs to another team.
            $originAccount = $this->resolveAccount($team, $attributes['origin_account_id'] ?? null);
            $destinationAccount = $this->resolveAccount($team, $attributes['destination_account_id'] ?? null);
            $originCurrency = $this->resolveCurrency($attributes['origin_currency_id'] ?? null);
            $destinationCurrency = $this->resolveCurrency($attributes['destination_currency_id'] ?? null);

            // Update the transfer itself, so the owned transactions copy
            // its new date and description.
            $transfer->fill($this->transferAttributes($attributes))->save();

            // Rewrite both legs in place, so they keep their ids.
            $transactions = [
                $this->writeTransaction($transfer, $originLeg, $originAccount, TransactionType::TransferOut, $originCurrency, $attributes['amount_sent'] ?? null, $attributes['origin_quote'] ?? null, $transfer->description),
                $this->writeTransaction($transfer, $destinationLeg, $destinationAccount, TransactionType::TransferIn, $destinationCurrency, $attributes['amount_received'] ?? null, $attributes['destination_quote'] ?? null, $transfer->description),
            ];

            // Create, update, or delete the fee charged on each side so
            // the rows match the submitted fees.
            $transactions[] = $this->writeFee($transfer, $originFee, $originAccount, $originCurrency, $attributes['origin_fee'] ?? null, $attributes['origin_quote'] ?? null);
            $transactions[] = $this->writeFee($transfer, $destinationFee, $destinationAccount, $destinationCurrency, $attributes['destination_fee'] ?? null, $attributes['destination_quote'] ?? null);

            // Drop the fees that are no longer charged.
            $transactions = array_filter($transactions);

            // Replace the tag set of the transfer with the submitted ids.
            $transfer->syncTags($tagIds);

            foreach ($transactions as $transaction) {
                // Replace the tag set of the owned transaction.
                $transaction->syncTags($tagIds);

                // Apply its new effect on the account balance.
                $this->applyBalance($team, $transaction);
            }

            // Return the updated transfer.
            return $transfer->refresh();
        }));
    }

    /**
     * Create, update, or delete the fee charged on one side of
     * the transfer.
     */
    protected function writeFee(Transfer $transfer, ?Transaction $fee, Account $account, Currency $currency, mixed $amount, mixed $quote): ?Transaction
    {
        // When no fee is charged, drop the row that held the old one.
        if (blank($amount)) {
            $fee?->delete();

            return null;
        }

        return $this->writeTransaction($transfer, $fee, $account, TransactionType::Expense, $currency, $amount, $quote, trans('Transfer fee'));
    }

    /**
     * Rewrite one of the transactions owned by the transfer, creating
     * it when the transfer does not own it yet.
     */
    protected function writeTransaction(Transfer $transfer, ?Transaction $transaction, Account $account, TransactionType $type, Currency $currency, mixed $amount, mixed $quote, ?string $description): Transaction
    {
        $attributes = $this->transactionAttributes($transfer, $account, $currency, $type, $amount, $quote, $description);

        // When the transfer does not own the transaction yet, create it.
        if (! $transaction instanceof Transaction) {
            /** @var \App\Modules\Core\Finance\Models\Team\Transaction $created */
            $created = $account->transactions()->create($attributes);

            return $created;
        }

        // Otherwise rewrite it in place, so it keeps its id.
        $transaction->fill($attributes)->save();

        return $transaction;
    }
}
