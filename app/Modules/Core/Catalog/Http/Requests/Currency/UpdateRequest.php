<?php

namespace App\Modules\Core\Catalog\Http\Requests\Currency;

use App\Modules\Core\Catalog\Models\Currency;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
    public function rules(#[RouteParameter('currency')] Currency $currency): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Currency::class)
                    ->ignore($currency),
            ],
            'decimal_places' => [
                'required',
                'integer',
                'min:0',
                'max:8',
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
        ];
    }
}
