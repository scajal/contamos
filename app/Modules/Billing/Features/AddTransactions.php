<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Models\Team;
use Laravel\Pennant\Attributes\Name;

#[Name('transactions:create')]
class AddTransactions
{
    /**
     * Get the enum object associated with
     * the feature.
     */
    public function enum(): Feature
    {
        return Feature::AddTransactions;
    }

    /**
     * Check if the feature is enabled for the
     * given team in its current plan, based on the
     * usage and limit.
     */
    public function resolve(Team $team): bool
    {
        return Feature::AddTransactions->enabled($team);
    }
}
