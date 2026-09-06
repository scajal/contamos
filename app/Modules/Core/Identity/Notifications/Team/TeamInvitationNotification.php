<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TeamInvitation $invitation,
    ) {}

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
    public function toMail(object $notifiable): MailMessage
    {
        // Load the team and the inviter, since the queued
        // notification only carries the invitation key.
        $this->invitation->loadMissing(['inviter', 'team']);

        return (new MailMessage)
            ->subject(trans('You have been invited to join :team', ['team' => $this->invitation->team->name]))
            ->line(trans(':inviter has invited you to join the team :team.', [
                'inviter' => $this->invitation->inviter->name,
                'team' => $this->invitation->team->name,
            ]))
            ->action(trans('Accept invitation'), route('identity.invitations.show', ['invitation' => $this->invitation]))
            ->line(trans('This invitation expires on :date.', [
                'date' => $this->invitation->expires_at?->toFormattedDateString() ?? '',
            ]));
    }
}
