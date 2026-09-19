<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;

/**
 * Shared account and currency resolution, and owned transaction
 * attributes, for the transfer create and update actions.
 */
trait BuildsTransferTransactions
{
    /**
     * Resolve one of the accounts of the team, failing when the
     * account belongs to another team.
     */
    protected function resolveAccount(Team $team, mixed $accountId): Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
        $account = $team->accounts()
            ->whereKey($accountId)
            ->firstOrFail();

        return $account;
    }

    /**
     * Resolve the currency of one of the sides of
     * the transfer.
     */
    protected function resolveCurrency(mixed $currencyId): Currency
    {
        /** @var \App\Modules\Core\Catalog\Models\Currency $currency */
        $currency = Currency::query()
            ->whereKey($currencyId)
            ->firstOrFail();

        return $currency;
    }

    /**
     * Get the attributes of the transfer row itself, keeping out the
     * ones it does not submit so an omitted field is left alone
     * rather than wiped.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function transferAttributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(['description', 'occurred_at']));
    }

    /**
     * Build the attributes of a transaction owned by
     * the transfer.
     *
     * @return array<string, mixed>
     */
    protected function transactionAttributes(Transfer $transfer, Account $account, Currency $currency, TransactionType $type, mixed $amount, mixed $quote, ?string $description): array
    {
        $transaction = new Transaction;

        return [
            $transaction->account()->getForeignKeyName() => $account->getKey(),
            'amount' => $amount,
            $transaction->currency()->getForeignKeyName() => $currency->getKey(),
            'decimal_places' => $currency->decimal_places,
            'description' => $description,
            'occurred_at' => $transfer->occurred_at,
            'quote' => $quote,
            $transaction->transfer()->getForeignKeyName() => $transfer->getKey(),
            'type' => $type,
            $transaction->user()->getForeignKeyName() => $transfer->getAttribute($transfer->user()->getForeignKeyName()),
        ];
    }
}
