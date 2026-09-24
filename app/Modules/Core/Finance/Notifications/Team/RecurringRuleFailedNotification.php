<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Notifications\Team;

use App\Modules\Core\Finance\Enums\RecurringRule\FailureReason;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use App\Modules\Support\Notifications\Messages\BrandedMailMessage;

/**
 * The email telling the Owner and Admins that a recurring
 * rule could not generate its occurrence, and why.
 */
class RecurringRuleFailedNotification extends TeamNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        Team $team,
        public RecurringRule $rule,
        public FailureReason $reason,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the switch that decides whether a member receives
     * the email.
     */
    public function type(): NotificationType
    {
        return NotificationType::RecurringFailures;
    }

    /**
     * Write the subject and the body of the email onto the
     * given message, with the reason explained in the locale
     * the email is rendered in.
     */
    protected function compose(BrandedMailMessage $message, User $notifiable): BrandedMailMessage
    {
        // A rule without a description goes by its type, the
        // way the rule listing names it.
        $name = $this->rule->description ?: $this->rule->type->getLabel();

        return $message
            ->subject(trans('A recurring transaction failed on :team', ['team' => $this->team->name]))
            ->line(trans('The recurring transaction ":name" on :team could not be recorded.', [
                'name' => $name,
                'team' => $this->team->name,
            ]))
            ->line($this->reason->getLabel())
            ->line(trans('It is tried again every hour until the cause is fixed.'))
            ->action(trans('View recurring transaction'), route('finance.teams.recurring-rules.show', [
                'recurringRule' => $this->rule,
                'team' => $this->team,
            ]));
    }
}
