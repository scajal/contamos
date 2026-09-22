<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The month's movements every breakdown starts from, and the
 * reading of the sums a driver hands back. The tag and the
 * category aggregates only differ in what they group by,
 * so everything else is shared.
 */
trait SumsMonthlyActivity
{
    /**
     * The month's income and expenses of the team's active
     * accounts, the shape every aggregate starts from.
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
