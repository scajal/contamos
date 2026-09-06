<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\Team\Invitation;

use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

class RenewAndResendTeamInvitation
{
    /**
     * Renew and re-send the provided invitation
     * to the invitee.
     */
    public function using(TeamInvitation $teamInvitation): void
    {
        // Regenerate the code and push the expiration forward.
        $teamInvitation->renew();

        // Email the invitation to the invitee again.
        Notification::route('mail', $teamInvitation->email)
            ->notify(new TeamInvitationNotification($teamInvitation));
    }
}
