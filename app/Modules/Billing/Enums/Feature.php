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
     * Represents how far back a team may open its own
     * history. The plan's limit is the number of months
     * reachable, counting the current one.
     *
     * @see \App\Modules\Billing\Features\ViewHistory
     */
    case ViewHistory = 'history:view';

    /**
     * Represents choosing the role a team's members carry.
     * A switch feature: the plan includes it or it does not.
     *
     * @see \App\Modules\Billing\Features\AssignRoles
     */
    case AssignRoles = 'members:roles';

    /**
     * Represents setting a monthly budget per tag. A switch
     * feature: the plan includes it or it does not.
     *
     * @see \App\Modules\Billing\Features\ManageBudgets
     */
    case ManageBudgets = 'budgets:manage';

    /**
     * Represents scheduling a transaction to repeat on its own.
     * A switch feature: the plan includes it or it does not.
     *
     * @see \App\Modules\Billing\Features\RecurTransactions
     */
    case RecurTransactions = 'transactions:recur';

    /**
     * Represents opening the team's activity log. A switch
     * feature: the plan includes it or it does not.
     *
     * @see \App\Modules\Billing\Features\ViewActivity
     */
    case ViewActivity = 'activity:view';

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
            self::ManageBudgets => trans('Budgets'),
            self::RecurTransactions => trans('Recurring transactions'),
            self::ViewActivity => trans('Activity log'),
            self::ViewHistory => trans('Months of history'),
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
            /** @var \App\Modules\Billing\Features\AssignRoles|\App\Modules\Billing\Features\ManageBudgets|\App\Modules\Billing\Features\RecurTransactions|\App\Modules\Billing\Features\ViewActivity $feature */
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
            self::AssignRoles, self::ManageBudgets, self::RecurTransactions, self::ViewActivity => true,
            self::AddAccounts, self::AddMembers, self::AddTeams, self::ViewHistory => false,
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

        /** @var \App\Modules\Billing\Features\AddAccounts|\App\Modules\Billing\Features\AddMembers|\App\Modules\Billing\Features\AddTeams|\App\Modules\Billing\Features\ViewHistory $feature */
        $feature = Pennant::instance($this);

        return $feature->limitOn($plan);
    }
}
