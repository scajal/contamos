<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\ResolvesOpeningBalances;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use ResolvesOpeningBalances;

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
        $currencyIds = $this->input('currency_ids', []);

        if (! is_array($currencyIds)) {
            return [];
        }

        return array_values(array_filter($currencyIds, is_string(...)));
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
            'quotes' => [
                'sometimes',
                'array:'.implode(',', $this->currencyIds()),
            ],
            'type' => [
                'required',
                'string',
                Rule::enum(AccountType::class),
            ],
        ];

        // Add rules for each currency.
        foreach ($this->currencyIds() as $currencyId) {
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
