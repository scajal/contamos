<?php

namespace App\Modules\Billing\Enums;

use App\Modules\Core\Identity\Models\Team;
use Filament\Support\Contracts\HasLabel;

enum Feature: string implements HasLabel
{
    /**
     * Check if the feature is enabled for the
     * given team in its current plan, based on the
     * usage and limit.
     */
    public function enabled(Team $team): bool
    {
        return match ($this) {
            // For now, all the features are enabled.
            default => true,
        };
    }

    /**
     * Get the label for the feature.
     */
    public function getLabel(): string
    {
        return match ($this) {
            //
        };
    }

    /**
     * For the given plan, return the limit
     * for the feature we're checking for.
     */
    public function limitOn(Plan $plan): ?int
    {
        return match ($this) {
            default => null,
        };
    }

    /**
     * For a given team, return the current usage
     * of the feature we're checking for.
     */
    public function usage(Team $team): int
    {
        return match ($this) {
            default => 0,
        };
    }
}
