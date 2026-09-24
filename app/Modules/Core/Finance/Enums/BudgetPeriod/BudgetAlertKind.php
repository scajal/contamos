<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\BudgetPeriod;

use Filament\Support\Contracts\HasLabel;

/**
 * The thresholds of a budget the team is emailed about when
 * a month crosses them: the share it asked to be warned at,
 * and the amount itself.
 */
enum BudgetAlertKind: string implements HasLabel
{
    /**
     * Represents the month reaching the share of the pot the
     * team asked to be warned at.
     */
    case Warning = 'warning';

    /**
     * Represents the month spending more than the pot.
     */
    case Over = 'over';

    /**
     * Whether a month with the given progress has crossed the
     * threshold. The warning is reached once the share is
     * met, while going over means spending past the whole
     * pot. A pot of nothing has no share to cross.
     *
     * @param  array{percentage: float|null, warning_threshold: int|null}  $progress
     */
    public function isMetBy(array $progress): bool
    {
        $percentage = $progress['percentage'];

        if ($percentage === null) {
            return false;
        }

        return match ($this) {
            self::Warning => $progress['warning_threshold'] !== null && $percentage >= $progress['warning_threshold'],
            self::Over => $percentage > 100,
        };
    }

    /**
     * Get the label for the alert kind.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Warning => trans('Warning'),
            self::Over => trans('Over budget'),
        };
    }
}
