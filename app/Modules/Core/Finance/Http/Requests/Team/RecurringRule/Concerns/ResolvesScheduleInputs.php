<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\Concerns;

use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Shared template and schedule validation for the recurring rule Store
 * and Update requests. The template half mirrors the transaction store
 * request, since a rule is the transaction it will generate. The rules
 * themselves live in
 * \App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\Concerns\ValidatesRecurringRules,
 * which the MCP tools share.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesScheduleInputs
{
    use ValidatesRecurringRules;

    /**
     * Normalise the money and the time of day, which the form submits
     * as HH:mm, before validation reads them.
     */
    protected function prepareScheduleForValidation(): void
    {
        $this->merge(Arr::only($this->normalizeRecurringRuleInput($this->all()), ['amount', 'time_of_day']));
    }

    /**
     * Get the validation rules the rule's template and schedule share.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function scheduleRules(Team $team): array
    {
        return $this->recurringRuleRules($team, $this->all(), $this->rule());
    }

    /**
     * The rule the request is updating. A request creating one
     * updates none.
     */
    protected function rule(): ?RecurringRule
    {
        return null;
    }

    /**
     * Get the validated tag ids for the rule.
     *
     * @return array<int, string>
     */
    public function tagIds(): array
    {
        /** @var array<int, mixed> $tagIds */
        $tagIds = $this->validated('tag_ids', []);

        return array_values(array_filter($tagIds, is_string(...)));
    }

    /**
     * Get the rule attributes the action writes.
     *
     * @return array<string, mixed>
     */
    public function ruleAttributes(): array
    {
        return $this->safe($this->recurringRuleAttributeKeys());
    }
}
