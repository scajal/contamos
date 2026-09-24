<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;

/**
 * The email telling the Owner and Admins that somebody
 * joined the team.
 */
class MemberJoinedNotification extends MemberChangeNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        Team $team,
        public string $memberName,
        public Role $role,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the subject of the email.
     */
    protected function subject(): string
    {
        return trans(':name joined :team', [
            'name' => $this->memberName,
            'team' => $this->team->name,
        ]);
    }

    /**
     * Get the sentence that says what changed. The role is
     * the one the member acts as, labelled in the locale the
     * email is rendered in.
     */
    protected function sentence(): string
    {
        return trans(':name joined :team as :role.', [
            'name' => $this->memberName,
            'role' => $this->team->effectiveRole($this->role)?->getLabel() ?? '',
            'team' => $this->team->name,
        ]);
    }
}
