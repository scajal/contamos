<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Objects\OpeningBalance;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Shared opening-balance validation and resolution for the account
 * Store and Update requests.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesOpeningBalances
{
    /**
     * The selected account type.
     */
    protected function accountType(): ?AccountType
    {
        return AccountType::tryFrom($this->string('type')->toString());
    }

    /**
     * Get the rate the user typed for the currency, overriding
     * the catalog quote, if any was submitted.
     */
    protected function explicitQuote(string $currencyId): ?string
    {
        $quote = $this->input("quotes.{$currencyId}");

        return is_numeric($quote) && (float) $quote > 0 ? (string) $quote : null;
    }

    /**
     * Reject an opening balance amount for a currency with no
     * quote observed at or before the resolved occurred at instant,
     * unless the user typed one.
     */
    protected function hasQuoteAsOf(Currency $currency): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currency): void {
            // Skip empty or non-positive amounts; they create nothing.
            if (! is_numeric($value) || (float) $value <= 0) {
                return;
            }

            // Skip when the user typed a rate for this currency.
            if ($this->explicitQuote((string) $currency->getKey()) !== null) {
                return;
            }

            // Fail when no quote can be resolved as of the occurred at instant.
            if (! Instant::resolveQuote($currency, $this->input('occurred_at')) instanceof Quote) {
                $fail(trans('No exchange rate exists for :currency as of the selected date.', ['currency' => $currency->code]));
            }
        };
    }

    /**
     * Get the opening balances to create, keyed by currency id,
     * for currencies with a positive amount and a resolvable
     * quote, catalog or user-typed.
     *
     * @return array<string, \App\Modules\Core\Finance\Objects\OpeningBalance>
     */
    public function openingBalances(): array
    {
        /** @var array<string, mixed> $balances */
        $balances = $this->safe(['opening_balances'])['opening_balances'] ?? [];

        /** @var string|null $occurredAt */
        $occurredAt = $this->safe(['occurred_at'])['occurred_at'] ?? null;

        if (! is_string($occurredAt)) {
            return [];
        }

        $resolved = [];

        foreach ($balances as $currencyId => $amount) {
            // Skip empty or non-positive amounts; they create nothing.
            if (! is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            // If the currency is not found, skip it.
            if (! ($currency = Currency::query()->find($currencyId)) instanceof Currency) {
                continue;
            }

            // Resolve the quote, catalog or user-typed.
            if (($quote = $this->explicitQuote((string) $currencyId) ?? Instant::resolveQuote($currency, $occurredAt)?->rate) === null) {
                continue;
            }

            // Add the opening balance to the resolved array.
            $resolved[$currencyId] = new OpeningBalance(
                amount: (string) $amount,
                quote: (string) $quote,
                occurredAt: $occurredAt,
            );
        }

        return $resolved;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'occurred_at' => Instant::toUtc($this->input('occurred_at'), $this->input('timezone'))
                ?? $this->input('occurred_at')
                ?? CarbonImmutable::now()->toIso8601String(),
        ]);
    }
}
