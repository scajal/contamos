<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
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
     * Get the submitted currency ids.
     *
     * @return array<int, string>
     */
    protected function currencyIds(): array
    {
        /** @var mixed $currencyIds */
        $currencyIds = $this->input('currency_ids', []);

        if (! is_array($currencyIds)) {
            return [];
        }

        return array_values(array_filter($currencyIds, is_string(...)));
    }

    /**
     * Reject an opening balance amount for a currency with no
     * quote observed at or before the resolved occurred at instant.
     */
    protected function hasQuoteAsOf(Currency $currency): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currency): void {
            // Skip empty or non-positive amounts; they create nothing.
            if (! is_numeric($value) || (float) $value <= 0) {
                return;
            }

            // Fail when no quote can be resolved as of the occurred at instant.
            if (! Instant::resolveQuote($currency, $this->input('occurred_at')) instanceof Quote) {
                $fail(trans('No exchange rate exists for :currency as of the selected date.', ['currency' => $currency->code]));
            }
        };
    }

    /**
     * Get the opening balances to create for the account, keyed
     * by currency id, for currencies with a positive amount and
     * a resolvable quote.
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
            if (! is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            $currency = Currency::query()->find($currencyId);

            if (! $currency instanceof Currency) {
                continue;
            }

            $quote = Instant::resolveQuote($currency, $occurredAt);

            if (! $quote instanceof Quote) {
                continue;
            }

            $resolved[$currencyId] = [
                'amount' => (string) $amount,
                'occurred_at' => $occurredAt,
                'quote' => (string) $quote->rate,
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
    public function rules(): array
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
                'array:'.implode(',', $this->currencyIds()),
            ],
            'type' => [
                'required',
                'string',
                Rule::enum(AccountType::class),
            ],
        ];

        foreach ($this->currencyIds() as $currencyId) {
            $currency = Currency::query()->find($currencyId);

            $rules["opening_balances.{$currencyId}"] = [
                'nullable',
                $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gte:0',
                ...($currency instanceof Currency ? [$this->hasQuoteAsOf($currency)] : []),
            ];
        }

        return $rules;
    }
}
