<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;

/**
 * Sums what a month moved under each tag and currency. The
 * monthly report breaks the month down with these figures and
 * a budget's progress is measured against them, so both read
 * the same numbers from the same query.
 */
trait SumsTaggedActivity
{
    /**
     * The month's income and expenses of the team's active
     * accounts, the shape every tag aggregate starts from.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected function monthly(Team $team, CarbonImmutable $month): Builder
    {
        $transaction = new Transaction;

        return $team->transactions()
            ->getQuery()
            ->whereIn($transaction->qualifyColumn('type'), [
                TransactionType::Expense->value,
                TransactionType::Income->value,
            ])
            ->whereRelation('account', new Account()->qualifyColumn('is_active'), true)
            ->occurredFrom($month->startOfMonth()->toDateString())
            ->occurredUntil($month->endOfMonth()->toDateString());
    }

    /**
     * Sum the month's income and expenses per tag and currency.
     * A transaction counts in full under every tag it carries,
     * and a tag the team has since retired still reports the
     * months it was used in.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected function taggedTotals(Team $team, CarbonImmutable $month): EloquentCollection
    {
        $transaction = new Transaction;
        $tag = new Tag;
        $tags = $transaction->tags();
        $currencyKey = $transaction->currency()->getForeignKeyName();

        return $this->monthly($team, $month)
            ->select([
                $transaction->qualifyColumn($currencyKey),
                $tag->qualifyColumn($tag->getKeyName()).' as tag_id',
                $tag->qualifyColumn('name').' as tag_name',
            ])
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
                $tag->qualifyColumn('name'),
            )
            ->get();
    }

    /**
     * The raw select that sums the amounts of one transaction
     * type, along with the type it is bound to.
     *
     * @param  literal-string  $alias
     * @return array{0: literal-string, 1: array<int, string>}
     */
    protected function sumOfType(TransactionType $type, string $alias): array
    {
        return [
            'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS '.$alias,
            [$type->value],
        ];
    }

    /**
     * Read an aggregated amount as a decimal string, since
     * drivers hand sums back as strings, integers or floats.
     *
     * @return numeric-string
     */
    protected function decimal(mixed $amount): string
    {
        $value = $this->text($amount);

        return is_numeric($value) ? $value : '0';
    }

    /**
     * Read a raw column value as a string, since drivers
     * type aggregated and key columns differently.
     */
    protected function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
