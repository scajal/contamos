<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Exception;

/**
 * Reads the category period a tag period is nested under as
 * one of its sub-budgets. Only a tag budget nests, only under
 * a category budget of the same team and currency, and the
 * nesting is informative: a parent never caps what its
 * sub-budgets add up to.
 */
trait NestsBudgetPeriods
{
    /**
     * Resolve the parent a period is nested under from the key
     * it was written with, or null when it stands on its own.
     * A parent the team cannot nest under is refused.
     */
    protected function guardParent(Team $team, Category|Tag $target, string $currencyId, mixed $parentId): ?BudgetPeriod
    {
        // No parent means a plain budget.
        if (blank($parentId)) {
            return null;
        }

        // A key that is not a string has escaped validation.
        if (! is_string($parentId)) {
            throw new Exception('A budget period parent must be named by its key.');
        }

        // Only a tag budget can be a sub-budget.
        if (! $target instanceof Tag) {
            throw new Exception('Only a tag budget can be nested under a category budget.');
        }

        /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod|null $parent */
        $parent = $team->budgets()->whereKey($parentId)->first();

        // A parent of another team would nest money the team
        // never sees.
        if (! $parent instanceof BudgetPeriod) {
            throw new Exception('A budget period cannot be nested under a budget of another team.');
        }

        // Only a category budget can hold sub-budgets.
        if (! $parent->category instanceof Category) {
            throw new Exception('A budget period can only be nested under a category budget.');
        }

        // Both sides must count the same money.
        if ($parent->getAttribute($parent->currency()->getForeignKeyName()) !== $currencyId) {
            throw new Exception('A budget period cannot be nested under a budget in another currency.');
        }

        return $parent;
    }

    /**
     * The column that pins a period under its parent, empty
     * for a plain budget.
     *
     * @return array<string, int|string|null>
     */
    protected function parentAttributes(?BudgetPeriod $parent): array
    {
        return [
            new BudgetPeriod()->parent()->getForeignKeyName() => $parent?->getKey(),
        ];
    }
}
