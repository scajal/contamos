<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Reports\Team;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Reports\Team\Concerns\SumsCategorizedActivity;
use App\Modules\Core\Finance\Reports\Team\Concerns\SumsTaggedActivity;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Eloquent\Builder;

/**
 * Totals of a team's income and expenses over any range of
 * dates, grouped by category, account or tag and kept apart
 * per currency. It sums with the same queries the monthly
 * report breaks a month down with, so a range covering a
 * month reads the same figures.
 */
class PeriodSummary
{
    use SumsCategorizedActivity;
    use SumsTaggedActivity;

    /**
     * The income and expenses of the team's active accounts
     * between the dates, both included, narrowed to one type,
     * account or currency when given.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>
     */
    public function movements(Team $team, string $from, string $to, ?TransactionType $type = null, ?string $accountId = null, ?string $currencyId = null): Builder
    {
        $transaction = new Transaction;

        return $this->activityBetween($team, $from, $to)
            ->when($type, fn (Builder $query, TransactionType $type): Builder => $query->where($transaction->qualifyColumn('type'), $type->value))
            ->when($accountId, fn (Builder $query, string $id): Builder => $query->where($transaction->qualifyColumn($transaction->account()->getForeignKeyName()), $id))
            ->when($currencyId, fn (Builder $query, string $id): Builder => $query->where($transaction->qualifyColumn($transaction->currency()->getForeignKeyName()), $id));
    }

    /**
     * Sum the movements per category and currency. A movement
     * carries one category, so the rows add up to the total.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $movements
     * @return array<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}>
     */
    public function byCategory(Builder $movements): array
    {
        $currencyKey = new Transaction()->currency()->getForeignKeyName();

        return $this->categorizedSums(clone $movements)
            ->map(function (Transaction $row) use ($currencyKey): array {
                $category = $row->getAttribute('category');
                $category = $category instanceof Category ? $category : (Category::tryFrom($this->text($category)) ?? Category::Other);

                return $this->row($row, $currencyKey, $category->value, $category->getLabel());
            })
            ->values()
            ->all();
    }

    /**
     * Sum the movements per account and currency.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $movements
     * @return array<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}>
     */
    public function byAccount(Team $team, Builder $movements): array
    {
        $transaction = new Transaction;
        $currencyKey = $transaction->currency()->getForeignKeyName();
        $accountKey = $transaction->account()->getForeignKeyName();

        $rows = (clone $movements)
            ->select([
                $transaction->qualifyColumn($currencyKey),
                $transaction->qualifyColumn($accountKey),
            ])
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->groupBy(
                $transaction->qualifyColumn($currencyKey),
                $transaction->qualifyColumn($accountKey),
            )
            ->get();

        // The names of the accounts, read once for every row.
        /** @var array<string, string> $names */
        $names = $team->accounts()
            ->whereKey($rows->map(fn (Transaction $row): string => $this->text($row->getAttribute($accountKey)))->unique()->all())
            ->pluck('name', new Account()->getKeyName())
            ->all();

        return $rows
            ->map(fn (Transaction $row): array => $this->row(
                $row,
                $currencyKey,
                $id = $this->text($row->getAttribute($accountKey)),
                $names[$id] ?? null,
            ))
            ->values()
            ->all();
    }

    /**
     * Sum the movements per tag and currency, plus one row per
     * currency for the money carrying no tag. A movement counts
     * in full under every tag it carries, so the rows may add
     * up to more than the total.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>  $movements
     * @return array<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}>
     */
    public function byTag(Builder $movements): array
    {
        $transaction = new Transaction;
        $currencyKey = $transaction->currency()->getForeignKeyName();

        $tagged = $this->taggedSums(clone $movements)
            ->map(fn (Transaction $row): array => $this->row(
                $row,
                $currencyKey,
                $this->text($row->getAttribute('tag_id')),
                $this->text($row->getAttribute('tag_name')),
            ));

        // Money carrying no tag at all still has to show up, so
        // it is summed apart under a null tag.
        $untagged = (clone $movements)
            ->select($transaction->qualifyColumn($currencyKey))
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->whereDoesntHave('tags')
            ->groupBy($transaction->qualifyColumn($currencyKey))
            ->get()
            ->map(fn (Transaction $row): array => $this->row($row, $currencyKey, null, null));

        return [...$tagged->values()->all(), ...$untagged->values()->all()];
    }

    /**
     * One summed row, read off the aggregate the driver
     * handed back.
     *
     * @return array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}
     */
    protected function row(Transaction $row, string $currencyKey, ?string $id, ?string $name): array
    {
        return [
            'currency_id' => $this->text($row->getAttribute($currencyKey)),
            'id' => $id,
            'name' => $name,
            'income' => $this->decimal($row->getAttribute('income')),
            'expenses' => $this->decimal($row->getAttribute('expenses')),
        ];
    }
}
