<?php

namespace App\Modules\Billing\Features;

use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('users:create')]
class AddMembers
{
    /**
     * Resolve whether the provided team
     * has access to the feature.
     */
    public function resolve(Team $team): bool
    {
        return ($limit = $team->plan()->limits()['members']) === null || $limit > $team->users()->count();
    }
}
