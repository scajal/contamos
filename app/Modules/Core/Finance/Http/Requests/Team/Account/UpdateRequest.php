<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * The selected account type.
     */
    protected function accountType(): ?AccountType
    {
        return AccountType::tryFrom($this->string('type')->toString());
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
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
     * Get the currency ids being newly attached to the account,
     * i.e. the submitted currency ids that are not currently
     * attached to it.
     *
     * @return array<int, string>
     */
    protected function newlyAttachedCurrencyIds(Account $account): array
    {
        if (! is_array($currencyIds = $this->input('currency_ids', []))) {
            return [];
        }

        /** @var array<int, string> $attached */
        $attached = array_filter($account->currencies->pluck(new Currency()->getKeyName())->all(), is_string(...));

        return array_values(array_diff(array_filter($currencyIds, is_string(...)), $attached));
    }

    /**
     * Get the opening balances to create for the newly attached
     * currencies, keyed by currency id, for currencies with a
     * positive amount and a resolvable quote.
     *
     * @return array<string, array{amount: string, quote: string, occurred_at: string}>
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
            $resolved[$currencyId] = [
                'amount' => (string) $amount,
                'occurred_at' => $occurredAt,
                'quote' => (string) $quote,
            ];
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('account')] Account $account): array
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
                    ->where(fn (Builder $query): Builder => $this->accountType() === AccountType::Crypto ? $query->where('type', CurrencyType::Cryptocurrency) : $query->where('type', '!=', CurrencyType::Cryptocurrency)),
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
                'array:'.implode(',', $this->newlyAttachedCurrencyIds($account)),
            ],
            'quotes' => [
                'sometimes',
                'array:'.implode(',', $this->newlyAttachedCurrencyIds($account)),
            ],
            'type' => [
                'required',
                'string',
                Rule::enum(AccountType::class),
            ],
        ];

        foreach ($this->newlyAttachedCurrencyIds($account) as $currencyId) {
            $currency = Currency::query()->find($currencyId);

            $rules["opening_balances.{$currencyId}"] = [
                'nullable',
                $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gte:0',
                ...($currency instanceof Currency ? [$this->hasQuoteAsOf($currency)] : []),
            ];

            $rules["quotes.{$currencyId}"] = [
                'nullable',
                'decimal:0,18',
            ];
        }

        return $rules;
    }
}
