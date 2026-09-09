<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Filament\Support\Contracts\HasLabel;
use Laravel\Pennant\Feature as Pennant;

/**
 * The plan-limited features. Each case names a Pennant feature
 * class under \App\Modules\Billing\Features that owns the per-plan
 * limit and resolves whether the scope is still under it.
 */
enum Feature: string implements HasLabel
{
    /**
     * Represents adding accounts to a team.
     *
     * @see \App\Modules\Billing\Features\AddAccounts
     */
    case AddAccounts = 'accounts:create';

    /**
     * Represents a member joining a team by accepting an
     * invitation. Invitations themselves are unlimited.
     *
     * @see \App\Modules\Billing\Features\AddMembers
     */
    case AddMembers = 'members:create';

    /**
     * Represents creating teams on the free plan. A
     * user may belong to a limited number of free teams.
     *
     * @see \App\Modules\Billing\Features\AddTeams
     */
    case AddTeams = 'teams:create';

    /**
     * Check if the feature is active for the given
     * scope through Pennant.
     */
    public function enabled(Team|User $scope): bool
    {
        return Pennant::for($scope)->active($this);
    }

    /**
     * Get the label for the feature.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::AddAccounts => trans('Accounts per team'),
            self::AddMembers => trans('Members per team'),
            self::AddTeams => trans('Free teams per user'),
        };
    }

    /**
     * For the given plan, return the limit for the
     * feature. Null means unlimited. The limit lives on
     * the Pennant feature class.
     */
    public function limitOn(Plan $plan): ?int
    {
        /** @var \App\Modules\Billing\Features\AddAccounts|\App\Modules\Billing\Features\AddMembers|\App\Modules\Billing\Features\AddTeams $feature */
        $feature = Pennant::instance($this);

        return $feature->limitOn($plan);
    }
}
