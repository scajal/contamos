<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Notifications\Messages\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * An email a team sends its Owner and Admins, which each of
 * them may switch off per team. It is queued, rendered in the
 * recipient's locale, and carries the links to manage the
 * switches and to turn this one off without signing in.
 *
 * @see \App\Modules\Core\Identity\Models\Team::notificationRecipients()
 */
abstract class TeamNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Drop the email instead of failing when the team is gone
     * by the time the queue gets to it.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Team $team,
    ) {}

    /**
     * Get the switch that decides whether a member receives
     * the email.
     */
    abstract public function type(): NotificationType;

    /**
     * Write the subject and the body of the email onto the
     * given message, which already carries the footer links.
     */
    abstract protected function compose(BrandedMailMessage $message, User $notifiable): BrandedMailMessage;

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
    public function toMail(User $notifiable): BrandedMailMessage
    {
        // Link the footer to the switches and to the one-click
        // way out of this kind of email.
        $message = (new BrandedMailMessage)
            ->manageNotificationsUsing(route('identity.teams.settings.notifications.edit', ['team' => $this->team]))
            ->unsubscribeUsing($this->type()->unsubscribeUrl($notifiable, $this->team));

        return $this->compose($message, $notifiable);
    }
}
