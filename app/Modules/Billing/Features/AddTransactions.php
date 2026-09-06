<?php

namespace App\Modules\Billing\Features;

use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('transactions:create')]
class AddTransactions
{
    /**
     * Resolve whether the provided team
     * has access to the feature.
     */
    public function resolve(Team $team): bool
    {
        return ($limit = $team->plan()->limits()['transactionsPerMonth']) === null || $limit > $team->planUsage('transactionsPerMonth');
    }
}
