<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Exception;

/**
 * Keeps a budget unambiguous by refusing two periods that cover
 * the same month on the same target in the same currency, and
 * by refusing a stretch that ends before it starts.
 */
trait GuardsBudgetPeriodOverlap
{
    use ResolvesBudgetTargets;

    /**
     * Refuse a stretch that a sibling period already covers, or
     * one whose months run backwards. The period being written
     * is left out of the comparison so an edit may keep its
     * own months.
     */
    protected function guardAgainstOverlap(
        Team $team,
        Category|Tag $target,
        string $currencyId,
        CarbonImmutable $from,
        ?CarbonImmutable $until,
        ?BudgetPeriod $except = null,
    ): void {
        $from = $from->startOfMonth();
        $until = $until?->startOfMonth();

        // A stretch cannot close before it opens.
        if ($until instanceof CarbonImmutable && $until->lessThan($from)) {
            throw new Exception('A budget period cannot end before it starts.');
        }

        $siblings = $this->whereTarget($team->budgets()->getQuery(), $target)
            ->where(new BudgetPeriod()->currency()->getForeignKeyName(), $currencyId)
            ->when($except instanceof BudgetPeriod, fn ($query) => $query->whereKeyNot($except))
            ->get();

        // Two periods covering the same month would leave the
        // month with two budgets and no way to choose.
        foreach ($siblings as $sibling) {
            if ($sibling->overlaps($from, $until)) {
                throw new Exception('A budget period cannot overlap another one on the same target and currency.');
            }
        }
    }
}
