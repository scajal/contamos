<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Notifications\Messages\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The email telling a team's Owner that the team dropped
 * from Pro to Free, what that changes and where to upgrade
 * again. It is not switchable, so it carries no manage or
 * unsubscribe links.
 */
class ProEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Drop the job instead of failing when the team is gone
     * by the time the queue gets to it.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new notification instance, queued only once
     * the drop it reports has been committed.
     */
    public function __construct(
        public Team $team,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): BrandedMailMessage
    {
        $team = $this->team->name;

        // Spell out the Free caps from the features that own
        // them, so the email never drifts from the plan.
        return (new BrandedMailMessage)
            ->subject(trans(':team is back on the Free plan', ['team' => $team]))
            ->line(trans('The Pro plan of :team has ended, so the team is now on the Free plan.', ['team' => $team]))
            ->line(trans('Budgets stop tracking spending and recurring transactions are no longer created.'))
            ->line(trans('Only the last :months months of history are visible. Older entries stay where they are.', [
                'months' => Feature::ViewHistory->limitOn(Plan::Free),
            ]))
            ->line(trans('The team can have up to :accounts active accounts and :members members. Nothing over those limits was removed, but you cannot add more.', [
                'accounts' => Feature::AddAccounts->limitOn(Plan::Free),
                'members' => Feature::AddMembers->limitOn(Plan::Free),
            ]))
            ->action(trans('Upgrade to Pro'), route('billing.teams.subscriptions.upgrade', ['team' => $this->team]))
            ->line(trans('Upgrading again brings everything back as it was.'));
    }
}
