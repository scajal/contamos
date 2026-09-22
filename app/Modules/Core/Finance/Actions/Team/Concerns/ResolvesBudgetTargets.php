<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Exception;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads what a budget period follows. A period targets a
 * category or one of the team's tags, never both and never
 * neither, and every action that writes one says so the
 * same way.
 */
trait ResolvesBudgetTargets
{
    /**
     * Refuse a target the team cannot budget: a tag belonging
     * to another team, or no target at all.
     */
    protected function guardTarget(Team $team, Category|Tag|null $target): Category|Tag
    {
        // A period with no target has nothing to measure.
        if ($target === null) {
            throw new Exception('A budget period cannot be written without a category or a tag.');
        }

        // A tag of another team would budget money the team
        // never sees.
        if ($target instanceof Tag && $target->getAttribute($target->team()->getForeignKeyName()) !== $team->getKey()) {
            throw new Exception('A budget period cannot follow a tag of another team.');
        }

        return $target;
    }

    /**
     * The columns that pin a period to its target, with the
     * column of the other kind left empty.
     *
     * @return array<string, \App\Modules\Core\Finance\Enums\Transaction\Category|int|string|null>
     */
    protected function targetAttributes(Category|Tag $target): array
    {
        return [
            'category' => $target instanceof Category ? $target : null,
            new BudgetPeriod()->tag()->getForeignKeyName() => $target instanceof Tag ? $target->getKey() : null,
        ];
    }

    /**
     * Narrow a query over budget periods to the ones following
     * the same target.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected function whereTarget(Builder $query, Category|Tag $target): Builder
    {
        if ($target instanceof Category) {
            return $query->where('category', $target);
        }

        return $query->where(new BudgetPeriod()->tag()->getForeignKeyName(), $target->getKey());
    }
}
