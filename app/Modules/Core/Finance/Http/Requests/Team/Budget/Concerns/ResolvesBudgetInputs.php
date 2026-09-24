<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Budget\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Reads the months, target, currency and warning threshold a
 * budget is written with, and builds the rules that keep a
 * budget unambiguous. Every method takes the submitted
 * values as a plain array, so the web forms and the MCP
 * tools validate alike.
 */
trait ResolvesBudgetInputs
{
    /**
     * The month format a budget's bounds are submitted in.
     */
    protected const string MONTH_FORMAT = 'Y-m';

    /**
     * The rules a new budget period is validated with.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function budgetStoreRules(Team $team, array $input): array
    {
        return [
            'amount' => [
                'required',
                'decimal:0,18',
                'gt:0',
            ],
            'category' => [
                'required_if:target_kind,'.BudgetTargetKind::Category->value,
                'prohibited_unless:target_kind,'.BudgetTargetKind::Category->value,
                'string',
                Rule::enum(Category::class),
            ],
            new BudgetPeriod()->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                $this->heldCurrency($team),
            ],
            'from_month' => [
                'required',
                'string',
                'date_format:'.self::MONTH_FORMAT,
                $this->freeMonths($team, $input),
            ],
            'target_kind' => [
                'required',
                Rule::enum(BudgetTargetKind::class),
            ],
            new BudgetPeriod()->tag()->getForeignKeyName() => [
                'required_if:target_kind,'.BudgetTargetKind::Tag->value,
                'prohibited_unless:target_kind,'.BudgetTargetKind::Tag->value,
                'string',
                $this->teamTag($team),
            ],
            'until_month' => [
                'sometimes',
                'nullable',
                'string',
                'date_format:'.self::MONTH_FORMAT,
            ],
            'warning_threshold' => $this->warningThresholdRules(),
        ];
    }

    /**
     * The rules a change to a budget period is validated
     * with. What a period follows is fixed: moving a budget
     * to another category or tag is a new budget, not an
     * edit.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function budgetUpdateRules(Team $team, BudgetPeriod $budget, array $input): array
    {
        return [
            'amount' => [
                'required',
                'decimal:0,18',
                'gt:0',
            ],
            $budget->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                $this->heldCurrency($team),
            ],
            'from_month' => [
                'required',
                'string',
                'date_format:'.self::MONTH_FORMAT,
                $this->freeMonths($team, $input, $budget),
            ],
            'until_month' => [
                'sometimes',
                'nullable',
                'string',
                'date_format:'.self::MONTH_FORMAT,
            ],
            'warning_threshold' => $this->warningThresholdRules(),
        ];
    }

    /**
     * The rules the share of the month's pot a warning fires
     * at is validated with: a whole percentage short of the
     * full budget, or nothing for no warning.
     *
     * @return array<int, string>
     */
    protected function warningThresholdRules(): array
    {
        return [
            'sometimes',
            'nullable',
            'integer',
            'between:1,99',
        ];
    }

    /**
     * Parse one of the submitted months, or null when it was
     * left out or is not a month at all.
     *
     * @param  array<array-key, mixed>  $input
     */
    protected function submittedMonth(array $input, string $attribute): ?CarbonImmutable
    {
        $value = $input[$attribute] ?? null;

        if (! is_string($value) || blank($value)) {
            return null;
        }

        return rescue(
            fn (): ?CarbonImmutable => CarbonImmutable::createFromFormat('!'.self::MONTH_FORMAT, $value)?->startOfMonth(),
            report: false,
        );
    }

    /**
     * The category or tag the budget is being set on, taken
     * from the period on an edit and from the submitted
     * values on a create.
     *
     * @param  array<array-key, mixed>  $input
     */
    protected function budgetTarget(Team $team, array $input, ?BudgetPeriod $period = null): Category|Tag|null
    {
        // An edit never moves a budget, so the period itself
        // says what it follows.
        if ($period instanceof BudgetPeriod) {
            return $period->target();
        }

        $category = $input['category'] ?? null;

        if (is_string($category) && $category !== '') {
            return Category::tryFrom($category);
        }

        /** @var \App\Modules\Core\Finance\Models\Team\Tag|null $tag */
        $tag = $team->tags()
            ->whereKey($input[new BudgetPeriod()->tag()->getForeignKeyName()] ?? null)
            ->first();

        return $tag;
    }

    /**
     * Restrict the currency to the ones the team holds on an
     * active account, since a budget in any other currency
     * could never show progress.
     */
    protected function heldCurrency(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            // Anything but a key has already been rejected by
            // the rules that run before this one.
            if (! is_string($value)) {
                return;
            }

            $held = $team->heldCurrencies()
                ->pluck(new Currency()->getKeyName())
                ->all();

            if (! in_array($value, $held, true)) {
                $fail(trans('Your team does not hold this currency on an active account.'));
            }
        };
    }

    /**
     * Reject months that run backwards or that another period
     * on the same target and currency already covers.
     *
     * @param  array<array-key, mixed>  $input
     */
    protected function freeMonths(Team $team, array $input, ?BudgetPeriod $except = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team, $input, $except): void {
            $from = $this->submittedMonth($input, 'from_month');

            // Without a readable start there is nothing to
            // compare the rest against.
            if (! $from instanceof CarbonImmutable) {
                return;
            }

            $until = $this->submittedMonth($input, 'until_month');

            // A stretch cannot close before it opens.
            if ($until instanceof CarbonImmutable && $until->lessThan($from)) {
                $fail(trans('The last month cannot be before the first month.'));

                return;
            }

            $target = $this->budgetTarget($team, $input, $except);
            $currencyId = $input[new BudgetPeriod()->currency()->getForeignKeyName()] ?? null;

            // An unknown target or currency is somebody else's
            // rule to report.
            if ($target === null || ! is_string($currencyId)) {
                return;
            }

            $period = new BudgetPeriod;

            $siblings = $team->budgets()
                ->when(
                    $target instanceof Category,
                    fn ($query) => $query->where('category', $target),
                    fn ($query) => $query->where($period->tag()->getForeignKeyName(), $target instanceof Tag ? $target->getKey() : null),
                )
                ->where($period->currency()->getForeignKeyName(), $currencyId)
                ->when($except instanceof BudgetPeriod, fn ($query) => $query->whereKeyNot($except))
                ->get();

            foreach ($siblings as $sibling) {
                if ($sibling->overlaps($from, $until)) {
                    $fail($target instanceof Category
                        ? trans('This category already has a budget covering those months in that currency.')
                        : trans('This tag already has a budget covering those months in that currency.'));

                    return;
                }
            }
        };
    }

    /**
     * The rule that keeps a budget pointed at one of the
     * team's own tags.
     */
    protected function teamTag(Team $team): Exists
    {
        return Rule::exists(Tag::class, new Tag()->getKeyName())
            ->where(new Tag()->team()->getForeignKeyName(), $team->getKey());
    }
}
