<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Http\Requests\Currency\Quote;

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
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
     * @return array<int, \Spatie\QueryBuilder\AllowedFilter>
     */
    public function filters(): array
    {
        return [
            AllowedFilter::exact('source'),
            AllowedFilter::partial('search', 'observed_at'),
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
            'filter.search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'filter.source' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(QuoteSource::class),
            ],
        ];
    }
}
