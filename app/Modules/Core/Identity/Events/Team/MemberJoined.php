<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Events\Team;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberJoinedNotification;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user joined a team by accepting an invitation.
 */
class MemberJoined implements NotifiesTeam
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Team $team,
        public User $member,
        public Role $role,
    ) {}

    /**
     * Get the team the event happened on.
     */
    public function team(): Team
    {
        return $this->team;
    }

    /**
     * Get the user who caused the event, the one who joined.
     */
    public function causer(): User
    {
        return $this->member;
    }

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification
    {
        return new MemberJoinedNotification($this->team, $this->member->name, $this->role);
    }
}
