<?php

declare(strict_types=1);

namespace App\Modules\Administration\Http\Requests\Currency;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'quote_source' => [
                'required',
                'string',
                Rule::enum(QuoteSource::class),
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
