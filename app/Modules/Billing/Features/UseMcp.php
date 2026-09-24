<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('mcp:use')]
class UseMcp
{
    /**
     * Resolve whether AI assistants may reach the team through
     * the MCP server. This is a switch feature: the plan either
     * includes it or it does not. Every plan includes it today,
     * with the limits of the plan applying through each tool.
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
            Plan::Free => true,
            Plan::Pro => true,
        };
    }
}
