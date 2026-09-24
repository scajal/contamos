<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers;

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController
{
    /**
     * Turn the notification type off for the user on the team
     * and confirm it. The signed link from the email is the
     * only credential, so it works without a session, and
     * following it twice changes nothing.
     */
    public function unsubscribe(User $user, Team $team, NotificationType $type): Response
    {
        $membership = $user->membershipOn($team);

        // A user who left the team has nothing left to turn
        // off, so the page only says so.
        if ($membership instanceof Membership) {
            $team->users()->updateExistingPivot($user->getKey(), [
                'notification_preferences' => [
                    ...($membership->notification_preferences ?? []),
                    $type->value => false,
                ],
            ]);
        }

        return Inertia::render('identity/notifications/unsubscribe', [
            'isMember' => $membership instanceof Membership,
            'team' => $team->only(['name', 'slug']),
            'type' => $type->getLabel(),
        ]);
    }
}
