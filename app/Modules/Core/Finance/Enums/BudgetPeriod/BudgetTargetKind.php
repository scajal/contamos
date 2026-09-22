<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\BudgetPeriod;

use Filament\Support\Contracts\HasLabel;

/**
 * What a budget period measures. A period follows an area of
 * spending or a free marker, never both, and the kind is
 * what the forms ask for before anything else.
 */
enum BudgetTargetKind: string implements HasLabel
{
    /**
     * Represents a budget that follows a category, counting
     * every movement filed under it.
     */
    case Category = 'category';

    /**
     * Represents a budget that follows a tag, counting every
     * movement carrying it whatever its category.
     */
    case Tag = 'tag';

    /**
     * Get the label for the target kind.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Category => trans('Category'),
            self::Tag => trans('Tag'),
        };
    }
}
