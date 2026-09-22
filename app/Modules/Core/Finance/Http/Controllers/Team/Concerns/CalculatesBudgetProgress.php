<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out how much of a budget a month has used, whether the
 * budget follows a category or a tag. The monthly report and
 * the Budgets page both show the same figures, so they are
 * computed in one place.
 */
trait CalculatesBudgetProgress
{
    use SumsMonthlyActivity;

    /**
     * The scale the money strings are computed with, matching
     * the decimal cast on the amount columns.
     */
    protected const int BUDGET_SCALE = 18;

    /**
     * The team's budget periods that apply in the month, keyed
     * by target and currency. A team whose plan does not
     * include budgets has none, which is what hides them
     * on a downgrade without deleting anything.
     *
     * @return \Illuminate\Support\Collection<string, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected function budgetPeriodsFor(Team $team, CarbonImmutable $month): Collection
    {
        if (! Feature::ManageBudgets->enabled($team)) {
            return new Collection;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\BudgetPeriod> $periods */
        $periods = $team->budgets()
            ->covering($month)
            ->with('tag')
            ->get();

        return new Collection($periods->mapWithKeys(fn (BudgetPeriod $period): array => [
            $this->budgetKeyFor($period) => $period,
        ]));
    }

    /**
     * The key a period is looked up by, so one target budgeted
     * in two currencies keeps one entry per currency and a
     * tag key can never read as a category.
     */
    protected function budgetKey(BudgetTargetKind $kind, string $target, string $currencyId): string
    {
        return $kind->value.'|'.$target.'|'.$currencyId;
    }

    /**
     * The key a period itself is filed under, read from the
     * target it was written with.
     */
    protected function budgetKeyFor(BudgetPeriod $period): string
    {
        $target = $period->target();

        return $this->budgetKey(
            $period->targetKind(),
            $target instanceof Category ? $target->value : $this->text($target?->getKey()),
            $this->text($period->getAttribute($period->currency()->getForeignKeyName())),
        );
    }

    /**
     * The key the month's figures for a tag are filed under.
     */
    protected function tagBudgetKey(string $tagId, string $currencyId): string
    {
        return $this->budgetKey(BudgetTargetKind::Tag, $tagId, $currencyId);
    }

    /**
     * The key the month's figures for a category are filed
     * under.
     */
    protected function categoryBudgetKey(string $category, string $currencyId): string
    {
        return $this->budgetKey(BudgetTargetKind::Category, $category, $currencyId);
    }

    /**
     * What the month has made of a budget: the pot it had
     * to spend, how much of it went, what is left and how far
     * along that is. Income tagged into the budget enlarges
     * the pot, so a money injection shows up in what
     * remains. The percentage is not capped, so going
     * over reads past a hundred.
     *
     * @param  numeric-string  $income
     * @param  numeric-string  $expenses
     * @return array{amount: numeric-string, income: numeric-string, spent: numeric-string, remaining: numeric-string, percentage: float|null}
     */
    protected function budgetProgress(BudgetPeriod $period, string $income, string $expenses): array
    {
        $amount = (string) $period->amount;
        $pot = bcadd($amount, $income, self::BUDGET_SCALE);

        // A pot of nothing cannot be a share of anything, so
        // the page is given a dash instead of a division
        // by zero.
        $percentage = bccomp($pot, '0', self::BUDGET_SCALE) === 0
            ? null
            : (float) bcdiv(bcmul($expenses, '100', self::BUDGET_SCALE), $pot, 4);

        return [
            'amount' => $amount,
            'income' => $income,
            'spent' => $expenses,
            'remaining' => bcsub($pot, $expenses, self::BUDGET_SCALE),
            'percentage' => $percentage,
        ];
    }
}
