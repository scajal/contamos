<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\FiltersTransactions;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    use FiltersTransactions;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Validated filters with list values compacted for the query
     * string. When the team's plan hides older history, a date the
     * user asked for below the window start is shown raised to it,
     * so the filter bar says what actually applied. A request that
     * carried no date keeps none.
     *
     * @return array<string, mixed>
     */
    public function presentedFilters(?string $since = null): array
    {
        // Get the filters from the request.
        /** @var array<string, mixed> $filters */
        $filters = $this->validated('filter', []);

        // If the tags are an array, implode them into a string.
        if (isset($filters['tags']) && is_array($filters['tags'])) {
            $filters['tags'] = implode(',', array_values(array_filter($filters['tags'], is_string(...))));
        }

        // Raise a date the plan no longer reaches.
        if ($since !== null && is_string($from = $filters['from'] ?? null) && $from < $since) {
            $filters['from'] = $since;
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
        return $this->filterRules($team);
    }
}
