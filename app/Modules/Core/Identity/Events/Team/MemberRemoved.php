<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Events\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberRemovedNotification;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member was removed from a team by somebody else.
 */
class MemberRemoved implements NotifiesTeam
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Team $team,
        public string $memberName,
        public User $remover,
    ) {}

    /**
     * Get the team the event happened on.
     */
    public function team(): Team
    {
        return $this->team;
    }

    /**
     * Get the user who caused the event, the one who removed
     * the member.
     */
    public function causer(): User
    {
        return $this->remover;
    }

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification
    {
        return new MemberRemovedNotification($this->team, $this->memberName, $this->remover->name);
    }
}
