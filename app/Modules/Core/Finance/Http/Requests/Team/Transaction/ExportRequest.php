<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\FiltersTransactions;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Instant;
use DateTimeZone;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    use FiltersTransactions;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Membership is enforced by the route group middleware.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return [
            ...$this->filterRules($team),
            'timezone' => [
                'sometimes',
                'nullable',
                'string',
                'timezone:all',
            ],
        ];
    }

    /**
     * The timezone the exported dates and times are written in. A
     * request that carries none falls back to UTC, which is how
     * the transactions are stored.
     */
    public function timezone(): DateTimeZone
    {
        return Instant::zone($this->validated('timezone'));
    }
}
