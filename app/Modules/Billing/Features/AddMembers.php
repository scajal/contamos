<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('members:create')]
class AddMembers
{
    /**
     * Resolve whether another member may join the team
     * under its current plan. Invitations are unlimited;
     * the limit applies when an invitation is accepted.
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
            Plan::Free => 2,
            Plan::Pro => null,
        };
    }

    /**
     * Count the members the team currently has.
     */
    public function usage(Team $team): int
    {
        return $team->users()->count();
    }
}
