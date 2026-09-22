<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Sums what a month moved under each category and currency. A
 * movement carries one category, so this is a plain group by
 * with none of the double counting a tag pivot brings, and
 * the sums partition the month exactly.
 */
trait SumsCategorizedActivity
{
    use SumsMonthlyActivity;

    /**
     * Sum the month's income and expenses per category and
     * currency. A movement nobody filed under a category is
     * read as Other, the bucket for everything that fits
     * none of the areas, so no money falls out of the
     * breakdown and the rows still add up to the
     * month's totals.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected function categorizedTotals(Team $team, CarbonImmutable $month): EloquentCollection
    {
        $transaction = new Transaction;
        $currencyKey = $transaction->currency()->getForeignKeyName();

        return $this->monthly($team, $month)
            ->select($transaction->qualifyColumn($currencyKey))
            ->selectRaw(...$this->categoryOrOther())
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->groupBy($transaction->qualifyColumn($currencyKey))
            ->groupByRaw(...$this->categoryOrOther(false))
            ->get();
    }

    /**
     * The expression the rows are filed under, which reads a
     * movement with no category of its own as Other. The
     * select names it, the group by repeats it, so both
     * sides of the query agree on the bucket. Other is
     * written into the SQL rather than bound, because
     * MySQL prepares each placeholder as its own parameter
     * and then refuses the select as not matching the
     * group by under only_full_group_by.
     *
     * @return array{0: literal-string, 1: array<int, string>}
     */
    protected function categoryOrOther(bool $aliased = true): array
    {
        $other = Category::Other->value;

        return [
            $aliased ? "COALESCE(category, '{$other}') AS category" : "COALESCE(category, '{$other}')",
            [],
        ];
    }
}
