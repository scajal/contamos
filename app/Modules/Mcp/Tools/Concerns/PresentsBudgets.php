<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Reports\Team\Concerns\ReportsBudgetProgress;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Presents a team's budget periods the way the budget tools
 * return them, with the same status and current month
 * progress the Budgets page shows, and reads the currency
 * codes and budget ids the tools are given.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait PresentsBudgets
{
    use ReportsBudgetProgress;

    /**
     * Get the schema of the `budget` argument naming one of
     * the team's budget periods.
     *
     * @return array{budget: \Illuminate\JsonSchema\Types\Type}
     */
    protected function budgetSchema(JsonSchema $schema): array
    {
        return [
            'budget' => $schema->string()
                ->description('The id of the budget period, as listed by `list_budgets`.')
                ->required(),
        ];
    }

    /**
     * Find one of the team's budget periods by id. A period of
     * another team reads as not found.
     */
    protected function budgetOf(Team $team, mixed $id): BudgetPeriod
    {
        return $this->findOrFail($team->budgets(), $id, 'budget');
    }

    /**
     * Resolve a currency from its ISO code. Whether the team
     * holds it is left to the shared budget rules.
     */
    protected function currencyByCode(mixed $code): Currency
    {
        $currency = is_string($code) && Str::trim($code) !== ''
            ? Currency::query()->where('code', Str::upper(Str::trim($code)))->first()
            : null;

        if (! $currency instanceof Currency) {
            throw new ToolError('Unknown currency "'.(is_scalar($code) ? $code : '?').'". Pass an ISO code the team holds, such as "USD".');
        }

        return $currency;
    }

    /**
     * Present every one of the team's budget periods,
     * categories first and newest stretch first within each
     * target, as of the current month.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function presentBudgets(Team $team): array
    {
        $month = CarbonImmutable::now();
        $totals = $this->budgetedTotalsByKey($team, $month);
        $successors = $this->successorMonths($team);

        return $team->budgets()
            ->with(['currency', 'parent', 'tag'])
            ->get()
            ->sortBy(fn (BudgetPeriod $budget): string => implode('|', [
                $budget->category instanceof Category ? '0'.$budget->category->value : '1'.Str::lower($budget->tag->name ?? ''),
                $this->text($budget->getAttribute($budget->currency()->getForeignKeyName())),
                (string) (9999999999 - $budget->from_month->getTimestamp()),
            ]))
            ->map(fn (BudgetPeriod $budget): array => $this->presentBudget($budget, $month, $totals, $successors))
            ->values()
            ->all();
    }

    /**
     * Present one of the team's budget periods as of the
     * current month.
     *
     * @return array<string, mixed>
     */
    protected function presentOneBudget(Team $team, BudgetPeriod $budget): array
    {
        $month = CarbonImmutable::now();

        $budget->load(['currency', 'parent', 'tag']);

        return $this->presentBudget($budget, $month, $this->budgetedTotalsByKey($team, $month), $this->successorMonths($team));
    }

    /**
     * Present a budget period: what it follows, the category
     * budget it sits under if any, its amount, months and
     * warning threshold, its status and, for the stretch in
     * force, how the month is going against it.
     *
     * @param  \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>  $totals
     * @param  array<string, \Carbon\CarbonImmutable>  $successors
     * @return array<string, mixed>
     */
    protected function presentBudget(BudgetPeriod $budget, CarbonImmutable $month, Collection $totals, array $successors): array
    {
        $decimals = $budget->currency->decimal_places;
        $target = $budget->target();
        $progress = $this->monthProgress($budget, $month, $totals);

        return [
            'id' => $budget->getKey(),
            'target' => $target instanceof Category
                ? ['kind' => $budget->targetKind()->value, 'category' => $target->value, 'label' => $target->getLabel()]
                : ['kind' => $budget->targetKind()->value, 'tag' => $target?->only([$target->getKeyName(), 'name', 'is_active'])],
            'parent' => $budget->getAttribute($budget->parent()->getForeignKeyName()),
            'currency' => $budget->currency->code,
            'amount' => $this->amount($budget->amount, $decimals),
            'from_month' => $budget->from_month->format('Y-m'),
            'until_month' => $budget->until_month?->format('Y-m'),
            'warning_threshold' => $budget->warning_threshold,
            'status' => $budget->status($month, $this->isSuperseded($budget, $successors))->value,
            // Only the stretch in force reports on the month.
            'progress' => $progress === null ? null : [
                'month' => $month->format('Y-m'),
                'amount' => $this->amount($progress['amount'], $decimals),
                'income' => $this->amount($progress['income'], $decimals),
                'spent' => $this->amount($progress['spent'], $decimals),
                'remaining' => $this->amount($progress['remaining'], $decimals),
                'percentage' => $progress['percentage'],
            ],
        ];
    }
}
