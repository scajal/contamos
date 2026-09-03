<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Requests\Currency;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
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
            'filter' => [
                'sometimes',
                'array',
            ],
            'filter.is_active' => [
                'sometimes',
                'nullable',
                'boolean',
            ],
            'filter.search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'filter.type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(CurrencyType::class),
            ],
        ];
    }
}
