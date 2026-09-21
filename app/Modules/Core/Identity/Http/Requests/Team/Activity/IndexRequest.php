<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Requests\Team\Activity;

use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
            AllowedFilter::scope('subject', 'for_subject_type'),
            AllowedFilter::scope('causer', 'by_causer'),
            AllowedFilter::scope('from', 'occurred_from'),
            AllowedFilter::scope('to', 'occurred_until'),
        ];
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
            'filter.subject' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(ActivitySubject::class),
            ],
            'filter.causer' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists(Membership::class, new User()->getForeignKey())
                    ->where(new Team()->getForeignKey(), $team->getKey()),
            ],
            'filter.from' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
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
