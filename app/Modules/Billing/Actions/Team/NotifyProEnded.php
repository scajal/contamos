<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions\Team;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Notifications\Team\ProEndedNotification;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Email the Owner of a team whose Pro plan just ended, when
 * the team really dropped to Free. The caller claims the end
 * it reports first, so each drop is announced once.
 */
class NotifyProEnded
{
    /**
     * Email the Owner when the team is now on the Free plan,
     * and say whether the email went out.
     */
    public function using(Team $team): bool
    {
        // Another source of Pro, a grant by hand or a Paddle
        // subscription, may still stand, so the team did not
        // drop and there is nothing to tell.
        if ($team->plan() !== Plan::Free) {
            return false;
        }

        // Billing is the Owner's business, so only they hear
        // about it.
        if (! ($owner = $team->owner) instanceof User) {
            return false;
        }

        $owner->notify(new ProEndedNotification($team));

        return true;
    }
}
