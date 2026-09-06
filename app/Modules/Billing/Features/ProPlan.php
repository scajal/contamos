<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Core\Identity\Models\Team;

class ProPlan
{
    /**
     * Resolve whether the given team is on the
     * Pro plan.
     */
    public function resolve(Team $team): bool
    {
        return $team->subscribed();
    }
}
