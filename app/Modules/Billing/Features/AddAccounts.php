<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('accounts:create')]
class AddAccounts
{
    /**
     * Resolve whether the provided team
     * has access to the feature.
     */
    public function resolve(Team $team): bool
    {
        return ($limit = $team->plan()->limits()['accounts']) === null || $limit > $team->planUsage('accounts');
    }
}
