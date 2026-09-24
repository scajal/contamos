<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Enums\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\URL;

/**
 * The kinds of email a team sends its Owner and Admins.
 * Each one is a switch every Owner and Admin turns on or
 * off for themself, per team.
 *
 * @see \App\Modules\Core\Identity\Models\Membership::wantsNotification()
 */
enum NotificationType: string implements HasLabel
{
    /**
     * Represents the warning that a budget is running out
     * or went over.
     */
    case BudgetAlerts = 'budget_alerts';

    /**
     * Represents somebody joining or leaving the team, or
     * a member's role changing.
     */
    case MemberChanges = 'member_changes';

    /**
     * Represents the summary of the month that just ended.
     */
    case MonthlySummary = 'monthly_summary';

    /**
     * Represents a recurring transaction that could not be
     * recorded.
     */
    case RecurringFailures = 'recurring_failures';

    /**
     * Get the one-line explanation of what the switch
     * sends.
     */
    public function description(): string
    {
        return match ($this) {
            self::BudgetAlerts => trans('When a budget is about to run out or goes over.'),
            self::MemberChanges => trans('When somebody joins or leaves the team, or a role changes.'),
            self::MonthlySummary => trans("A summary of the team's money when a month ends."),
            self::RecurringFailures => trans('When a recurring transaction could not be recorded.'),
        };
    }

    /**
     * Get the label for the notification type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::BudgetAlerts => trans('Budget alerts'),
            self::MemberChanges => trans('Member changes'),
            self::MonthlySummary => trans('Monthly summary'),
            self::RecurringFailures => trans('Recurring failures'),
        };
    }

    /**
     * Get the link that turns the notification type off for
     * the user on the team without signing in. It is signed
     * but never expires, so an old email keeps working, and
     * it names the team by its key rather than its slug, so
     * renaming the team does not break it either.
     */
    public function unsubscribeUrl(User $user, Team $team): string
    {
        return URL::signedRoute('identity.notifications.unsubscribe', [
            'team' => $team->getKey(),
            'type' => $this->value,
            'user' => $user->getKey(),
        ]);
    }
}
