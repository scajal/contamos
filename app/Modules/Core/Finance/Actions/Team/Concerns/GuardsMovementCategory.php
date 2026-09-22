<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use Exception;

trait GuardsMovementCategory
{
    /**
     * Make sure the attributes carry a category the movement's type
     * can hold: exactly one for an income or an expense, and none
     * for a transfer leg or an opening balance.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function guardCategory(array $attributes, ?TransactionType $currentType = null): void
    {
        $type = $this->movementType($attributes) ?? $currentType;

        // Without a type there is nothing to hold the category
        // against, and the type itself is validated elsewhere.
        if (! $type instanceof TransactionType) {
            return;
        }

        $category = $this->movementCategory($attributes);
        $allowed = Category::casesFor($type);

        // A type recorded by hand always names its category, so the
        // data never fills itself in with "Other".
        if ($allowed !== [] && ! $category instanceof Category) {
            throw new Exception("A movement of type {$type->value} must carry a category.");
        }

        // A transfer leg and an opening balance are movements between
        // the team's own accounts, never spending.
        if ($allowed === [] && $category instanceof Category) {
            throw new Exception("A movement of type {$type->value} cannot carry a category.");
        }

        // The two lists do not overlap, so a salary can never be
        // filed as housing.
        if ($category instanceof Category && ! $category->allowedFor($type)) {
            throw new Exception("The category {$category->value} does not belong to a movement of type {$type->value}.");
        }
    }

    /**
     * Read the category out of the attributes, whether it
     * arrives as the enum or as its backing value.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function movementCategory(array $attributes): ?Category
    {
        $category = $attributes['category'] ?? null;

        return $category instanceof Category
            ? $category
            : (is_string($category) ? Category::tryFrom($category) : null);
    }

    /**
     * Read the type out of the attributes, whether it arrives
     * as the enum or as its backing value.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function movementType(array $attributes): ?TransactionType
    {
        $type = $attributes['type'] ?? null;

        return $type instanceof TransactionType
            ? $type
            : (is_string($type) ? TransactionType::tryFrom($type) : null);
    }
}
