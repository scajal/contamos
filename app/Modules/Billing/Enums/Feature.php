<?php

namespace App\Modules\Billing\Enums;

use App\Modules\Core\Identity\Models\Team;
use Filament\Support\Contracts\HasLabel;

enum Feature: string implements HasLabel
{
    /**
     * Represents the add accounts feature.
     */
    case AddAccounts = 'accounts:create';

    /**
     * Represents the add members feature.
     */
    case AddMembers = 'members:create';

    /**
     * Represents the add transactions feature.
     */
    case AddTransactions = 'transactions:create';

    /**
     * Check if the feature is enabled for the
     * given team in its current plan, based on the
     * usage and limit.
     */
    public function enabled(Team $team): bool
    {
        return match ($this) {
            // For now, all the features are enabled if the limit is null
            // or if the current usage is less than the limit.
            default => (is_int($limit = $this->limitOn($team->plan()))) ? $this->usage($team) < $limit : true,
        };
    }

    /**
     * Get the label for the feature.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::AddAccounts => trans('Accounts'),
            self::AddMembers => trans('Members'),
            self::AddTransactions => trans('Transactions (per month)'),
        };
    }

    /**
     * For the given plan, return the limit
     * for the feature we're checking for.
     */
    public function limitOn(Plan $plan): ?int
    {
        return match ($this) {
            self::AddAccounts => match ($plan) {
                // The free plan allows 1 account and
                // the pro plan allows unlimited accounts.
                Plan::Free => 1, Plan::Pro => null
            },
            self::AddMembers => match ($plan) {
                // The free plan allows 1 member and
                // the pro plan allows unlimited members.
                Plan::Free => 1, Plan::Pro => null
            },
            self::AddTransactions => match ($plan) {
                // The free plan allows 1 transactions per month and
                // the pro plan allows unlimited transactions.
                Plan::Free => 100, Plan::Pro => null
            },
        };
    }

    /**
     * For a given team, return the current usage
     * of the feature we're checking for.
     */
    public function usage(Team $team): int
    {
        return match ($this) {
            // Count the number of accounts that the
            // team currently has.
            self::AddAccounts => $team->accounts()->count(),

            // Count the number of members that the
            // team currently has.
            self::AddMembers => $team->users()->count(),

            // Count the number of transactions that the
            // team currently has in the current month.
            self::AddTransactions => $team->transactions()
                ->occurredFrom(now()->startOfMonth())
                ->occurredUntil(now()->endOfMonth())
                ->count(),
        };
    }
}
