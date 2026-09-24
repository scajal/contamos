<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Objects\OpeningBalance;
use App\Modules\Support\Helpers\Instant;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * The validation rules and opening-balance resolution of an
 * account, from plain values, shared by the account Store and
 * Update requests and by the MCP account tools so both validate
 * the same way.
 */
trait DefinesAccountRules
{
    /**
     * Get the rules an account's attributes must pass. Opening
     * balances and rates are only accepted for the given
     * currencies: every submitted one on creation, the newly
     * attached ones on update.
     *
     * @param  array<int, string>  $balanceCurrencyIds
     * @param  array<array-key, mixed>  $quotes
     * @return array<string, array<mixed>>
     */
    protected function accountRules(?AccountType $type, array $balanceCurrencyIds, array $quotes, mixed $occurredAt): array
    {
        $rules = [
            'currency_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'currency_ids.*' => [
                'required',
                Rule::exists(Currency::class, new Currency()->getKeyName())
                    ->where(fn (Builder $query): Builder => $type === AccountType::Crypto ? $query->where('type', CurrencyType::Cryptocurrency) : $query->where('type', '!=', CurrencyType::Cryptocurrency)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'occurred_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'opening_balances' => [
                'sometimes',
                'array:'.implode(',', $balanceCurrencyIds),
            ],
            'quotes' => [
                'sometimes',
                'array:'.implode(',', $balanceCurrencyIds),
            ],
            'type' => [
                'required',
                'string',
                Rule::enum(AccountType::class),
            ],
        ];

        // Add rules for each currency taking a balance.
        foreach ($balanceCurrencyIds as $currencyId) {
            $currency = Currency::query()->find($currencyId);

            $rules["opening_balances.{$currencyId}"] = [
                'nullable',
                $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gte:0',
                ...($currency instanceof Currency ? [$this->hasQuoteAsOf($currency, $this->explicitQuote($quotes, $currencyId), $occurredAt)] : []),
            ];

            $rules["quotes.{$currencyId}"] = [
                'nullable',
                'decimal:0,18',
            ];
        }

        return $rules;
    }

    /**
     * Get the rate typed for the currency, overriding the
     * catalog quote, if a positive one was given.
     *
     * @param  array<array-key, mixed>  $quotes
     */
    protected function explicitQuote(array $quotes, string $currencyId): ?string
    {
        $quote = $quotes[$currencyId] ?? null;

        return is_numeric($quote) && (float) $quote > 0 ? (string) $quote : null;
    }

    /**
     * Reject an opening balance amount for a currency with no
     * quote observed at or before the occurred at instant,
     * unless a rate was typed for it.
     */
    protected function hasQuoteAsOf(Currency $currency, ?string $explicitQuote, mixed $occurredAt): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currency, $explicitQuote, $occurredAt): void {
            // Skip empty or non-positive amounts; they create nothing.
            if (! is_numeric($value) || (float) $value <= 0) {
                return;
            }

            // Skip when a rate was typed for this currency.
            if ($explicitQuote !== null) {
                return;
            }

            // Fail when no quote can be resolved as of the occurred at instant.
            if (! Instant::resolveQuote($currency, $occurredAt) instanceof Quote) {
                $fail(trans('No exchange rate exists for :currency as of the selected date.', ['currency' => $currency->code]));
            }
        };
    }

    /**
     * Resolve the validated opening balances to create, keyed by
     * currency id, for currencies with a positive amount and a
     * resolvable quote, catalog or typed.
     *
     * @param  array<array-key, mixed>  $balances
     * @param  array<array-key, mixed>  $quotes
     * @return array<string, \App\Modules\Core\Finance\Objects\OpeningBalance>
     */
    protected function resolveOpeningBalances(array $balances, array $quotes, mixed $occurredAt): array
    {
        if (! is_string($occurredAt)) {
            return [];
        }

        $resolved = [];

        foreach ($balances as $currencyId => $amount) {
            $currencyId = (string) $currencyId;

            // Skip empty or non-positive amounts; they create nothing.
            if (! is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            // If the currency is not found, skip it.
            if (! ($currency = Currency::query()->find($currencyId)) instanceof Currency) {
                continue;
            }

            // Resolve the quote, catalog or typed.
            if (($quote = $this->explicitQuote($quotes, $currencyId) ?? Instant::resolveQuote($currency, $occurredAt)?->rate) === null) {
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
}
