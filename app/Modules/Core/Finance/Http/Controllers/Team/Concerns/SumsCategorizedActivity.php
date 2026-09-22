<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Sums what a month moved under each category and currency. A
 * movement carries one category, so this is a plain group by
 * with none of the double counting a tag pivot brings.
 */
trait SumsCategorizedActivity
{
    use SumsMonthlyActivity;

    /**
     * Sum the month's income and expenses per category and
     * currency, leaving out the movements nobody filed
     * under a category.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected function categorizedTotals(Team $team, CarbonImmutable $month): EloquentCollection
    {
        $transaction = new Transaction;
        $currencyKey = $transaction->currency()->getForeignKeyName();

        return $this->monthly($team, $month)
            ->select([
                $transaction->qualifyColumn($currencyKey),
                $transaction->qualifyColumn('category'),
            ])
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->whereNotNull($transaction->qualifyColumn('category'))
            ->groupBy(
                $transaction->qualifyColumn($currencyKey),
                $transaction->qualifyColumn('category'),
            )
            ->get();
    }
}
