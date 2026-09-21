<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('transactions:recur')]
class RecurTransactions
{
    /**
     * Resolve whether the team may schedule transactions to repeat.
     * This is a switch feature: the plan either includes it or it
     * does not. Recording a transaction by hand is never gated.
     */
    public function resolve(Team $team): bool
    {
        return $this->includedIn($team->plan());
    }

    /**
     * Whether the given plan includes the
     * feature.
     */
    public function includedIn(Plan $plan): bool
    {
        return match ($plan) {
            Plan::Free => false,
            Plan::Pro => true,
        };
    }
}
