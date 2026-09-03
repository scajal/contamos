<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Requests\Currency;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;

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
     * Get the list of allowed filters for the
     * request.
     *
     * @return array<string, \Spatie\QueryBuilder\AllowedFilter>
     */
    public function filters(): array
    {
        return [
            AllowedFilter::exact('type'),
            AllowedFilter::groupOr('search', [AllowedFilter::partial('name'), AllowedFilter::partial('code')]),
            AllowedFilter::scope('is_active'),
        ];
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
                Rule::in(['true', 'false']),
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
