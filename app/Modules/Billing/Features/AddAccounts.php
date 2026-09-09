<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('accounts:create')]
class AddAccounts
{
    /**
     * Resolve whether the team may add another account
     * under its current plan.
     */
    public function resolve(Team $team): bool
    {
        return ($limit = $this->limitOn($team->plan())) === null || $this->usage($team) < $limit;
    }

    /**
     * For the given plan, return the limit for the
     * feature. Null means unlimited.
     */
    public function limitOn(Plan $plan): ?int
    {
        return match ($plan) {
            Plan::Free => 3,
            Plan::Pro => null,
        };
    }

    /**
     * Count the active accounts the team currently has.
     * Deactivated accounts do not count toward the limit.
     */
    public function usage(Team $team): int
    {
        return $team->accounts()->isActive()->count();
    }
}
