<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Events\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Notifications\Team\MemberLeftNotification;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member left a team on their own, either from the team or
 * by deleting their account. The member goes by name, since
 * their account may be gone by the time the email is sent.
 */
class MemberLeft implements NotifiesTeam
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Team $team,
        public string $memberName,
    ) {}

    /**
     * Get the team the event happened on.
     */
    public function team(): Team
    {
        return $this->team;
    }

    /**
     * Get the user who caused the event. The one who left is
     * no longer on the team, so nobody needs leaving out.
     */
    public function causer(): null
    {
        return null;
    }

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification
    {
        return new MemberLeftNotification($this->team, $this->memberName);
    }
}
