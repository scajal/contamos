<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Container\Attributes\RouteParameter;
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
            AllowedFilter::scope('type', 'of_type'),
            AllowedFilter::exact('account', new Transaction()->account()->getForeignKeyName()),
            AllowedFilter::exact('currency', new Transaction()->currency()->getForeignKeyName()),
            AllowedFilter::groupOr('search', [AllowedFilter::exact('id'), AllowedFilter::partial('description')]),
            AllowedFilter::scope('from', 'occurred_from'),
            AllowedFilter::scope('tags', 'withAnyTags'),
            AllowedFilter::scope('to', 'occurred_until'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Validate that the tags are a string and not empty.
        if (! (is_string($tags = $this->input('filter.tags'))) || ! $tags) {
            return;
        }

        // Merge the tags into the request.
        $this->merge(['filter' => ['tags' => explode(',', $tags)]]);
    }

    /**
     * Validated filters with list values compacted for the query string.
     *
     * @return array<string, mixed>
     */
    public function presentedFilters(): array
    {
        // Get the filters from the request.
        /** @var array<string, mixed> $filters */
        $filters = $this->validated('filter', []);

        // If the tags are an array, implode them into a string.
        if (isset($filters['tags']) && is_array($filters['tags'])) {
            $filters['tags'] = implode(',', array_values(array_filter($filters['tags'], is_string(...))));
        }

        return $filters;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
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
            'filter.type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([...array_column(TransactionType::cases(), 'value'), Transaction::TRANSFER_TYPE]),
            ],
            'filter.account' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(Account::class, new Account()->getKeyName())
                    ->where(new Account()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'filter.currency' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
            ],
            'filter.from' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],
            'filter.tags' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'filter.tags.*' => [
                'required',
                'string',
                Rule::exists(Tag::class, new Tag()->getKeyName())
                    ->where(new Tag()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'filter.to' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('filter.from'), ['after_or_equal:filter.from']),
            ],
        ];
    }
}
