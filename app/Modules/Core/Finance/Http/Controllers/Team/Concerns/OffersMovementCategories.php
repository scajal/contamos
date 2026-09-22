<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team\Concerns;

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;

/**
 * The category options the transaction and recurring rule pages
 * present, read from the enum so the pages and the team panel
 * never drift from one list.
 */
trait OffersMovementCategories
{
    /**
     * Get the categories each recordable type offers, in the order
     * the picker shows them, with the label and the icon the
     * chip and the listing render.
     *
     * @return array<string, array<int, array{icon: string, label: string, value: string}>>
     */
    protected function categoryOptions(): array
    {
        $options = [];

        foreach (TransactionType::recordableCases() as $type) {
            $options[$type->value] = array_map(fn (Category $category): array => [
                'icon' => $category->icon(),
                'label' => $category->getLabel(),
                'value' => $category->value,
            ], Category::casesFor($type));
        }

        return $options;
    }
}
