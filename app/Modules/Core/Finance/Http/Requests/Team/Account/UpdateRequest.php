<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\ResolvesOpeningBalances;
use App\Modules\Core\Finance\Models\Team\Account;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
