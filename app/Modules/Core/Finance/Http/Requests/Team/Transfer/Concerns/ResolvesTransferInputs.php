<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transfer\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Helpers\Instant;

/**
 * Shared input preparation, per-side currency resolution, and
 * tag id reading for the transfer Store and Update requests,
 * on top of the transfer rules they share with the MCP tools.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesTransferInputs
{
    use DefinesTransferRules;

    /**
     * Get the currency of one of the sides of the
     * transfer, if any.
     */
    protected function currencyFor(string $key): ?Currency
    {
        return $this->transferCurrency($this->input($key));
    }

    /**
     * Get the currency the transfer arrives in, if any.
     */
    protected function destinationCurrency(): ?Currency
    {
        return $this->currencyFor('destination_currency_id');
    }

    /**
     * Get the currency the transfer leaves in, if any.
     */
    protected function originCurrency(): ?Currency
    {
        return $this->currencyFor('origin_currency_id');
    }

    /**
     * Prepare the data for validation, converting the submitted
     * instant to UTC and defaulting each side's blank quote from
     * the rate as of that instant.
     */
    protected function prepareForValidation(): void
    {
        $amount = new Amount;

        // A Spanish keyboard writes the decimal separator as a comma,
        // which the decimal rule rejects, so every submitted figure is
        // normalised before the blank quotes are filled in.
        $this->merge(array_map(
            $amount->normalize(...),
            $this->only([
                'amount_received',
                'amount_sent',
                'destination_fee',
                'destination_quote',
                'origin_fee',
                'origin_quote',
            ]),
        ));

        $occurredAt = Instant::toUtc($this->input('occurred_at'), $this->input('timezone')) ?? $this->input('occurred_at');

        $this->merge([
            'occurred_at' => $occurredAt,
            'destination_quote' => blank($destinationQuote = $this->input('destination_quote'))
                ? Instant::resolveQuote($this->destinationCurrency(), $occurredAt)?->rate
                : $destinationQuote,
            'origin_quote' => blank($originQuote = $this->input('origin_quote'))
                ? Instant::resolveQuote($this->originCurrency(), $occurredAt)?->rate
                : $originQuote,
        ]);
    }

    /**
     * Get the validated tag ids for the transfer.
     *
     * @return array<int, string>
     */
    public function tagIds(): array
    {
        $tagIds = $this->safe(['tag_ids'])['tag_ids'] ?? [];

        return is_array($tagIds) ? array_values(array_filter($tagIds, is_string(...))) : [];
    }
}
