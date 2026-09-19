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
 *
 * A quota feature carries a per-plan limit and the scope's usage.
 * A switch feature carries no limit: the plan either includes it
 * or it does not.
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
     * Represents choosing the role a team's members carry.
     * A switch feature: the plan includes it or it does not.
     *
     * @see \App\Modules\Billing\Features\AssignRoles
     */
    case AssignRoles = 'members:roles';

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
            self::AssignRoles => trans('Member roles'),
        };
    }

    /**
     * Whether the given plan includes the feature at all. A
     * switch feature answers for itself; a quota feature is
     * included when the plan allows more than none.
     */
    public function includedIn(Plan $plan): bool
    {
        if ($this->isSwitch()) {
            /** @var \App\Modules\Billing\Features\AssignRoles $feature */
            $feature = Pennant::instance($this);

            return $feature->includedIn($plan);
        }

        return ($limit = $this->limitOn($plan)) === null || $limit > 0;
    }

    /**
     * Whether the feature is a switch rather than a
     * quota, so it has no limit and no usage.
     */
    public function isSwitch(): bool
    {
        return match ($this) {
            self::AssignRoles => true,
            self::AddAccounts, self::AddMembers, self::AddTeams => false,
        };
    }

    /**
     * What the plan gives for the feature: the limit for a quota
     * feature, where null means unlimited, and whether the plan
     * includes it for a switch feature. This is the one place the
     * two shapes branch.
     */
    public function valueOn(Plan $plan): int|bool|null
    {
        return $this->isSwitch() ? $this->includedIn($plan) : $this->limitOn($plan);
    }

    /**
     * For the given plan, return the limit for the
     * feature. Null means unlimited, and a switch feature
     * has no limit. The limit lives on the Pennant
     * feature class.
     */
    public function limitOn(Plan $plan): ?int
    {
        if ($this->isSwitch()) {
            return null;
        }

        /** @var \App\Modules\Billing\Features\AddAccounts|\App\Modules\Billing\Features\AddMembers|\App\Modules\Billing\Features\AddTeams $feature */
        $feature = Pennant::instance($this);

        return $feature->limitOn($plan);
    }
}
