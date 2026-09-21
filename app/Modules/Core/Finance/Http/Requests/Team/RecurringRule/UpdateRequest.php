<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\RecurringRule;

use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\Concerns\ResolvesScheduleInputs;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
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
     * A rule that already started keeps the start date it was created
     * with, since that date is the anchor its past occurrences were
     * generated from. Only a start date being moved must not be
     * backdated.
     */
    protected function startsOnRule(): ValidationRule|Closure|string
    {
        $current = $this->rule()?->starts_on;

        return function (string $attribute, mixed $value, Closure $fail) use ($current): void {
            if ($current instanceof CarbonImmutable && is_string($value) && $current->isSameDay(CarbonImmutable::parse($value))) {
                return;
            }

            if (is_string($value) && CarbonImmutable::parse($value)->startOfDay()->greaterThanOrEqualTo(CarbonImmutable::now()->startOfDay())) {
                return;
            }

            $fail(trans('validation.after_or_equal', ['attribute' => $attribute, 'date' => trans('today')]));
        };
    }

    /**
     * The tag keys the rule already carries, which may stay on it
     * even after the tag was deactivated.
     *
     * @return array<int, string>
     */
    protected function attachedTagIds(): array
    {
        /** @var array<int, string> $keys */
        $keys = $this->rule()?->tags->modelKeys() ?? [];

        return $keys;
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
