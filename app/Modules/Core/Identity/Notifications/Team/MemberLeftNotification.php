<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Models\Team;

/**
 * The email telling the Owner and Admins that a member left
 * the team on their own.
 */
class MemberLeftNotification extends MemberChangeNotification
{
    /**
     * Create a new notification instance. The member goes by
     * name, since leaving may mean deleting their account.
     */
    public function __construct(
        Team $team,
        public string $memberName,
    ) {
        parent::__construct($team);
    }

    /**
     * Get the subject of the email.
     */
    protected function subject(): string
    {
        return trans(':name left :team', [
            'name' => $this->memberName,
            'team' => $this->team->name,
        ]);
    }

    /**
     * Get the sentence that says what changed.
     */
    protected function sentence(): string
    {
        return trans(':name left :team.', [
            'name' => $this->memberName,
            'team' => $this->team->name,
        ]);
    }
}
