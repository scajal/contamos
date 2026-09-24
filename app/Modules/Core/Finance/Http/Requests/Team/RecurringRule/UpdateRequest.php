<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\RecurringRule;

use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\Concerns\ResolvesScheduleInputs;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use ResolvesScheduleInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->prepareScheduleForValidation();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return $this->scheduleRules($team);
    }

    /**
     * The rule the request is updating.
     */
    protected function rule(): ?RecurringRule
    {
        $rule = $this->route('recurringRule');

        return $rule instanceof RecurringRule ? $rule : null;
    }
}
