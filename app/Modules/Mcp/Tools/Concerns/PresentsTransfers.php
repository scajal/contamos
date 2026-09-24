<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use Illuminate\Support\Str;

/**
 * Shapes a transfer for the MCP transfer tools: the transfer
 * itself with the leg leaving the origin account, the leg
 * arriving at the destination account, and the fee charged on
 * each side, if any.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 * @mixin \App\Modules\Mcp\Tools\Concerns\PresentsRecords
 */
trait PresentsTransfers
{
    /**
     * Present the transfer with both of its legs.
     *
     * @return array<string, mixed>
     */
    protected function presentTransfer(Transfer $transfer, ?string $timezone): array
    {
        $transfer->loadMissing('tags', 'user', 'transactions.account', 'transactions.currency');

        return [
            'id' => $transfer->getKey(),
            'description' => $transfer->description,
            ...$this->instant('occurred_at', $transfer->occurred_at, $timezone),
            'origin' => $this->presentTransferSide($transfer->originLeg(), $transfer->originFee()),
            'destination' => $this->presentTransferSide($transfer->destinationLeg(), $transfer->destinationFee()),
            'tags' => $transfer->tags
                ->sortBy('name')
                ->map(fn (Tag $tag): array => ['id' => $tag->getKey(), 'name' => $tag->name])
                ->values()
                ->all(),
            'recorded_by' => $transfer->user?->name,
        ];
    }

    /**
     * Present one side of the transfer: its leg and the fee
     * charged on the same account.
     *
     * @return array<string, mixed>|null
     */
    protected function presentTransferSide(?Transaction $leg, ?Transaction $fee): ?array
    {
        // A transfer always has both legs; a missing one is shown
        // as such rather than failing the call.
        if (! $leg instanceof Transaction) {
            return null;
        }

        return [
            'transaction_id' => $leg->getKey(),
            'account' => [
                'id' => $leg->account->getKey(),
                'name' => $leg->account->name,
            ],
            'currency' => $leg->currency->code,
            'amount' => $this->amount($leg->amount, $leg->decimal_places),
            'quote' => $this->transferQuote($leg->quote),
            'fee' => $fee instanceof Transaction ? $this->amount($fee->amount, $fee->decimal_places) : null,
            'fee_transaction_id' => $fee?->getKey(),
        ];
    }

    /**
     * Present an exchange rate without the trailing zeros of its
     * stored precision.
     */
    protected function transferQuote(mixed $quote): string
    {
        $quote = $this->amount($quote, 18);

        return Str::rtrim(Str::rtrim($quote, '0'), '.');
    }
}
