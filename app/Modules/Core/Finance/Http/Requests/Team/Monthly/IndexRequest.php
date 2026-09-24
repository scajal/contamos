<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Monthly;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'month' => [
                'sometimes',
                'nullable',
                'string',
                'date_format:Y-m',
            ],
        ];
    }

    /**
     * The month the report was asked for, or null when the
     * URL carries no month and the current one is meant.
     */
    public function selectedMonth(): ?CarbonImmutable
    {
        /** @var string|null $month */
        $month = $this->validated('month');

        if ($month === null || $month === '') {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m', $month);

        return $parsed?->startOfMonth();
    }

    /**
     * Send a mistyped month back to the current month instead
     * of bouncing the visit with validation errors, so a bad
     * link still lands on a usable page.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(to_route('finance.teams.monthly.index', [
            'team' => $this->route('team'),
        ]));
    }
}
