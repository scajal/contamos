<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Reports\Team\Concerns;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Reads where each of a team's budget periods stands: whether
 * a later stretch replaced it, and what the current month has
 * made of the one in force. The Budgets page and the MCP
 * budget listing report the same figures from here.
 */
trait ReportsBudgetProgress
{
    use CalculatesBudgetProgress;
    use SumsCategorizedActivity;
    use SumsTaggedActivity;

    /**
     * What the month has made of the period, or null when the
     * period is not the one in force that month.
     *
     * @param  \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>  $totals
     * @return array{amount: numeric-string, income: numeric-string, spent: numeric-string, remaining: numeric-string, percentage: float|null, warning_threshold: int|null}|null
     */
    protected function monthProgress(BudgetPeriod $budget, CarbonImmutable $month, Collection $totals): ?array
    {
        // Only the stretch in force has a month to report
        // against, so the others carry no progress.
        if (! $budget->covers($month)) {
            return null;
        }

        $activity = $totals->get($this->budgetKeyFor($budget), ['expenses' => '0', 'income' => '0']);

        return $this->budgetProgress($budget, $activity['income'], $activity['expenses']);
    }

    /**
     * Whether a later stretch took the target's budget over in
     * the same currency, which is what tells a replaced
     * period from one somebody stopped.
     *
     * @param  array<string, \Carbon\CarbonImmutable>  $successors
     */
    protected function isSuperseded(BudgetPeriod $budget, array $successors): bool
    {
        if (! $budget->until_month instanceof CarbonImmutable) {
            return false;
        }

        $latest = $successors[$this->budgetKeyFor($budget)] ?? null;

        return $latest instanceof CarbonImmutable && $latest->greaterThan($budget->from_month);
    }

    /**
     * The current month's income and expenses per target and
     * currency, keyed the way a budget looks them up.
     *
     * @return \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>
     */
    protected function budgetedTotalsByKey(Team $team, CarbonImmutable $month): Collection
    {
        // A movement counts under every tag it carries, once
        // more under the one category it was filed under, and
        // once more under each tag within that category, so
        // the three breakdowns are read side by side.
        $tagged = $this->taggedTotals($team, $month)
            ->mapWithKeys(fn (Transaction $row): array => [$this->budgetKeyOf($row) => $this->totalsOf($row)]);

        $categorized = $this->categorizedTotals($team, $month)
            ->mapWithKeys(fn (Transaction $row): array => [$this->budgetKeyOf($row) => $this->totalsOf($row)]);

        $nested = $this->nestedTotals($team, $month)
            ->mapWithKeys(fn (Transaction $row): array => [$this->budgetKeyOf($row) => $this->totalsOf($row)]);

        return $tagged->union($categorized)->union($nested);
    }

    /**
     * Sum the month's income and expenses per tag, category and
     * currency, which is what a sub-budget is measured
     * against: only the tag's movements filed under the
     * category it sits in.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected function nestedTotals(Team $team, CarbonImmutable $month): EloquentCollection
    {
        $transaction = new Transaction;
        $tag = new Tag;
        $tags = $transaction->tags();
        $currencyKey = $transaction->currency()->getForeignKeyName();

        return $this->monthly($team, $month)
            ->select([
                $transaction->qualifyColumn($currencyKey),
                $tag->qualifyColumn($tag->getKeyName()).' as tag_id',
            ])
            ->selectRaw(...$this->categoryOrOther())
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->join($tags->getTable(), function (JoinClause $join) use ($transaction, $tags): void {
                $join->on($tags->getQualifiedForeignPivotKeyName(), '=', $transaction->getQualifiedKeyName())
                    ->where($tags->getQualifiedMorphTypeName(), $transaction->getMorphClass());
            })
            ->join($tag->getTable(), $tag->getQualifiedKeyName(), '=', $tags->getQualifiedRelatedPivotKeyName())
            ->groupBy(
                $transaction->qualifyColumn($currencyKey),
                $tag->qualifyColumn($tag->getKeyName()),
            )
            ->groupByRaw(...$this->categoryOrOther(false))
            ->get();
    }

    /**
     * The income and expenses one aggregated row reports.
     *
     * @return array{expenses: numeric-string, income: numeric-string}
     */
    protected function totalsOf(Transaction $row): array
    {
        return [
            'expenses' => $this->decimal($row->getAttribute('expenses')),
            'income' => $this->decimal($row->getAttribute('income')),
        ];
    }

    /**
     * The budget an aggregated row reports against, read from
     * the breakdown the row came out of.
     */
    protected function budgetKeyOf(Transaction $row): string
    {
        // An aggregate row only carries the columns its own
        // breakdown selected, so the raw attributes are what
        // says which one it came out of.
        $attributes = $row->getAttributes();
        $currency = $this->text($attributes[new Transaction()->currency()->getForeignKeyName()] ?? null);

        // A row carrying both a tag and a category came out of
        // the nested breakdown.
        if (array_key_exists('tag_id', $attributes) && array_key_exists('category', $attributes)) {
            return $this->nestedBudgetKey($this->text($attributes['tag_id']), $this->text($attributes['category']), $currency);
        }

        if (array_key_exists('tag_id', $attributes)) {
            return $this->tagBudgetKey($this->text($attributes['tag_id']), $currency);
        }

        return $this->categoryBudgetKey($this->text($attributes['category'] ?? null), $currency);
    }

    /**
     * The latest month any of the team's budgets starts in, per
     * target and currency. A stretch that closed before one of
     * them was replaced rather than stopped.
     *
     * @return array<string, \Carbon\CarbonImmutable>
     */
    protected function successorMonths(Team $team): array
    {
        $latest = [];

        $periods = $team->budgets()->get();

        foreach ($periods as $one) {
            $key = $this->budgetKeyFor($one);

            if (! isset($latest[$key]) || $one->from_month->greaterThan($latest[$key])) {
                $latest[$key] = $one->from_month;
            }
        }

        return $latest;
    }

    /**
     * Where the period stands this month, telling a stretch a
     * later one replaced from one somebody stopped.
     */
    protected function currentBudgetStatus(Team $team, BudgetPeriod $budget): BudgetPeriodStatus
    {
        return $budget->status(CarbonImmutable::now(), $this->isSuperseded($budget, $this->successorMonths($team)));
    }

    /**
     * Whether the period can be paused: only an open-ended
     * stretch in force, as the Budgets page offers it.
     */
    protected function canPauseBudget(Team $team, BudgetPeriod $budget): bool
    {
        return ! $budget->until_month instanceof CarbonImmutable
            && $this->currentBudgetStatus($team, $budget) === BudgetPeriodStatus::Running;
    }

    /**
     * Whether the period can be resumed: only a stretch that
     * was stopped and nothing followed.
     */
    protected function canResumeBudget(Team $team, BudgetPeriod $budget): bool
    {
        return $this->currentBudgetStatus($team, $budget) === BudgetPeriodStatus::Paused;
    }
}
