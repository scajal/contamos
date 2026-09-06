<?php

declare(strict_types=1);

namespace App\Modules\Billing\Concerns;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Features\ProPlan;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Feature;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasPlan
{
    /**
     * Get the team's current plan. Resolved through the ProPlan
     * Pennant feature rather than querying Cashier directly, so
     * the plan-tier check is cached and centralized.
     */
    public function plan(): Plan
    {
        return Feature::for($this)->active(ProPlan::class) ? Plan::Pro : Plan::Free;
    }

    /**
     * Get the team's current usage for the given
     * plan-limited feature.
     */
    public function usage(string $feature): int
    {
        return match ($feature) {
            'accounts' => $this->accounts()->count(),
            'members' => $this->users()->count() + $this->invitations()->pending()->count(),
            'transactions' => $this->transactions()->whereBetween('created_at', [CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth()])->count(),
            default => 0,
        };
    }

    /**
     * Determine if the team is still within its plan's
     * limit for the given feature.
     */
    public function withinPlanLimit(string $feature): bool
    {
        return $this->plan()->allows($feature, $this->usage($feature));
    }
}
