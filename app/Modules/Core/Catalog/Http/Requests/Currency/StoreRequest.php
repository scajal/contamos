<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Requests\Currency;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\ExchangeRate\ExchangeRateSource;
use App\Modules\Core\Catalog\Models\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
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
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Currency::class),
            ],
            'decimal_places' => [
                'required',
                'integer',
                'min:0',
                'max:18',
            ],
            'exchange_rate_source' => [
                'required',
                'string',
                Rule::enum(ExchangeRateSource::class),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'symbol' => [
                'required',
                'string',
                'max:255',
            ],
            'type' => [
                'required',
                'string',
                Rule::enum(CurrencyType::class),
            ],
        ];
    }
}
