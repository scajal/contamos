<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Shared transaction filter allow-list, tag preparation, and filter
 * validation rules for the transaction Index and Export requests, so
 * the listing and its export can never drift apart.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait FiltersTransactions
{
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
            AllowedFilter::exact('category'),
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
     * Get the validation rules the filter values must satisfy, so an
     * unknown enum or an unparsable date is rejected before the query
     * builder ever sees it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(Team $team): array
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
                Rule::in(Transaction::typeFilters()),
            ],
            'filter.category' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(Category::class),
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
