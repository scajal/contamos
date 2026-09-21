<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Attributes\Name;

#[Name('history:view')]
class ViewHistory
{
    /**
     * Resolve whether the team may reach back over its whole
     * history. Every plan opens some months, so this answers
     * whether the window is unlimited rather than whether the
     * team is still under a limit.
     */
    public function resolve(Team $team): bool
    {
        return $this->limitOn($team->plan()) === null;
    }

    /**
     * For the given plan, return the number of months the
     * team may open, counting the current month. Null
     * means unlimited.
     */
    public function limitOn(Plan $plan): ?int
    {
        return match ($plan) {
            Plan::Free => 6,
            Plan::Pro => null,
        };
    }

    /**
     * Count the months the team has history for, from the month
     * of its earliest transaction to the current month, both
     * included. A team with no transactions has one month.
     */
    public function usage(Team $team): int
    {
        // Without a transaction there is nothing before
        // the current month.
        if (! is_scalar($earliest = $team->transactions()->min('occurred_at'))) {
            return 1;
        }

        return (int) CarbonImmutable::parse((string) $earliest)
            ->startOfMonth()
            ->diffInMonths(CarbonImmutable::now()->startOfMonth()) + 1;
    }
}
