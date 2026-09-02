<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'type' => [
                'required',
                'string',
                Rule::enum(AccountType::class),
            ],
        ];
    }

    /**
     * The selected account type.
     */
    protected function accountType(): ?AccountType
    {
        return AccountType::tryFrom($this->string('type')->toString());
    }
}
