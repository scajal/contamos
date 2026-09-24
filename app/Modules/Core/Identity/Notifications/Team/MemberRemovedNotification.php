<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Models\Team;

/**
 * The email telling the Owner and Admins that somebody
 * removed a member from the team.
 */
class MemberRemovedNotification extends MemberChangeNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        Team $team,
        public string $memberName,
        public string $causerName,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the subject of the email.
     */
    protected function subject(): string
    {
        return trans(':name was removed from :team', [
            'name' => $this->memberName,
            'team' => $this->team->name,
        ]);
    }

    /**
     * Get the sentence that says what changed.
     */
    protected function sentence(): string
    {
        return trans(':causer removed :name from :team.', [
            'causer' => $this->causerName,
            'name' => $this->memberName,
            'team' => $this->team->name,
        ]);
    }
}
