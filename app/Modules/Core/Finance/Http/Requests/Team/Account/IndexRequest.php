<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
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
            AllowedFilter::exact('currency', 'currencies.'.new Currency()->getKeyName()),
            AllowedFilter::exact('type'),
            AllowedFilter::partial('search', 'name'),
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
            'filter.currency' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
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
                Rule::enum(AccountType::class),
            ],
        ];
    }
}
