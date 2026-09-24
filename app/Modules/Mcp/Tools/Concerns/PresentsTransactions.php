<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Illuminate\Support\Str;

/**
 * Shapes a transaction for the MCP transaction tools: its signed
 * amount in its currency, its account, its tags, and the
 * transfer or recurring rule it belongs to, if any.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 * @mixin \App\Modules\Mcp\Tools\Concerns\PresentsRecords
 */
trait PresentsTransactions
{
    /**
     * Present the transaction. The amount is signed the way it
     * moves the balance: negative for an expense or money that
     * left the account.
     *
     * @return array<string, mixed>
     */
    protected function presentTransaction(Transaction $transaction, ?string $timezone): array
    {
        $transaction->loadMissing('account', 'currency', 'tags', 'user');

        $sign = $transaction->type->sign() === '-' ? '-' : '';

        return [
            'id' => $transaction->getKey(),
            'type' => $transaction->type->value,
            'category' => $transaction->category?->value,
            'amount' => $this->amount($sign.$transaction->amount, $transaction->decimal_places),
            'currency' => $transaction->currency->code,
            'account' => [
                'id' => $transaction->account->getKey(),
                'name' => $transaction->account->name,
            ],
            'description' => $transaction->description,
            ...$this->instant('occurred_at', $transaction->occurred_at, $timezone),
            'quote' => $this->transactionQuote($transaction->quote),
            'tags' => $transaction->tags
                ->sortBy('name')
                ->map(fn (Tag $tag): array => ['id' => $tag->getKey(), 'name' => $tag->name])
                ->values()
                ->all(),
            'transfer_id' => $transaction->transfer_id,
            'recurring_rule_id' => $transaction->recurring_rule_id,
            'recorded_by' => $transaction->user?->name,
        ];
    }

    /**
     * Present an exchange rate without the trailing zeros of its
     * stored precision.
     */
    protected function transactionQuote(mixed $quote): string
    {
        $quote = is_scalar($quote) ? (string) $quote : '';

        return Str::contains($quote, '.') ? Str::rtrim(Str::rtrim($quote, '0'), '.') : $quote;
    }
}
