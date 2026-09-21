<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out how much of a tag's budget a month has used. The
 * monthly report and the Budgets page both show the same
 * figures, so they are computed in one place.
 */
trait CalculatesBudgetProgress
{
    use SumsTaggedActivity;

    /**
     * The scale the money strings are computed with, matching
     * the decimal cast on the amount columns.
     */
    protected const int BUDGET_SCALE = 18;

    /**
     * The team's budget periods that apply in the month, keyed
     * by tag and currency. A team whose plan does not include
     * budgets has none, which is what hides them on a
     * downgrade without deleting anything.
     *
     * @return \Illuminate\Support\Collection<string, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected function budgetPeriodsFor(Team $team, CarbonImmutable $month): Collection
    {
        if (! Feature::ManageBudgets->enabled($team)) {
            return new Collection;
        }

        $period = new BudgetPeriod;
        $tagKey = $period->tag()->getForeignKeyName();
        $currencyKey = $period->currency()->getForeignKeyName();

        return BudgetPeriod::query()
            ->whereIn($tagKey, $team->tags()->select(new Tag()->getKeyName()))
            ->covering($month)
            ->with('tag')
            ->get()
            ->mapWithKeys(fn (BudgetPeriod $period): array => [
                $this->budgetKey(
                    $this->text($period->getAttribute($tagKey)),
                    $this->text($period->getAttribute($currencyKey)),
                ) => $period,
            ]);
    }

    /**
     * The key a period is looked up by, so a tag budgeted in
     * two currencies keeps one entry per currency.
     */
    protected function budgetKey(string $tagId, string $currencyId): string
    {
        return $tagId.'|'.$currencyId;
    }

    /**
     * What the month has made of a tag's budget: the pot it had
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
