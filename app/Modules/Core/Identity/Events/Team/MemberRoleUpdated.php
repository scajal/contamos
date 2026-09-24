<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Events\Team;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberRoleUpdatedNotification;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody changed the role a member carries on a team.
 */
class MemberRoleUpdated implements NotifiesTeam
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Team $team,
        public User $member,
        public User $updater,
        public Role $from,
        public Role $to,
    ) {}

    /**
     * Get the team the event happened on.
     */
    public function team(): Team
    {
        return $this->team;
    }

    /**
     * Get the user who caused the event, the one who changed
     * the role.
     */
    public function causer(): User
    {
        return $this->updater;
    }

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification
    {
        return new MemberRoleUpdatedNotification($this->team, $this->member->name, $this->updater->name, $this->from, $this->to);
    }
}
