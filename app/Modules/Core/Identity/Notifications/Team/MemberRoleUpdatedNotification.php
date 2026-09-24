<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;

/**
 * The email telling the Owner and Admins that somebody
 * changed a member's role.
 */
class MemberRoleUpdatedNotification extends MemberChangeNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        Team $team,
        public string $memberName,
        public string $causerName,
        public Role $from,
        public Role $to,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the subject of the email.
     */
    protected function subject(): string
    {
        return trans(":name's role changed on :team", [
            'name' => $this->memberName,
            'team' => $this->team->name,
        ]);
    }

    /**
     * Get the sentence that says what changed, with both
     * roles labelled in the locale the email is rendered in.
     */
    protected function sentence(): string
    {
        return trans(":causer changed :name's role on :team from :old to :new.", [
            'causer' => $this->causerName,
            'name' => $this->memberName,
            'new' => $this->to->getLabel(),
            'old' => $this->from->getLabel(),
            'team' => $this->team->name,
        ]);
    }
}
