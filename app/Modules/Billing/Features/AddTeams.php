<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Laravel\Pennant\Attributes\Name;

#[Name('teams:create')]
class AddTeams
{
    /**
     * Resolve whether the user may create another team. A new
     * team always starts on the free plan, so the user is
     * checked against the free plan's limit.
     */
    public function resolve(User $user): bool
    {
        return ($limit = $this->limitOn(Plan::Free)) === null || $this->usage($user) < $limit;
    }

    /**
     * For the given plan, return the limit for the
     * feature. Null means unlimited.
     */
    public function limitOn(Plan $plan): ?int
    {
        return match ($plan) {
            Plan::Free => 1,
            Plan::Pro => null,
        };
    }

    /**
     * Count the free teams the user belongs to.
     */
    public function usage(User $user): int
    {
        return $user->teams()
            ->with('subscriptions.items')
            ->get()
            ->filter(fn (Team $team): bool => $team->plan() === Plan::Free)
            ->count();
    }
}
