<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Listeners\Team;

use App\Modules\Core\Identity\Events\Team\NotifiesTeam;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Email the Owner and Admins of a team about something that
 * happened on it, sparing its causer and whoever switched
 * that kind of email off.
 */
class SendTeamNotification implements ShouldQueue
{
    /**
     * Drop the job instead of failing when a model the event
     * carries is gone by the time the queue gets to it.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Handle the event.
     */
    public function handle(NotifiesTeam $event): void
    {
        $notification = $event->notification();

        // Resolve who wants this kind of email now, rather than
        // when the event happened.
        $recipients = $event->team()->notificationRecipients($notification->type(), $event->causer());

        Notification::send($recipients, $notification);
    }
}
