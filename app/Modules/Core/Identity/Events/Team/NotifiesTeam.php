<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Events\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamNotification;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Something that happened on a team that its Owner and Admins
 * hear about by email. It is dispatched only once the
 * transaction around it commits, and one queued listener
 * sends every event of this kind.
 *
 * @see \App\Modules\Core\Identity\Listeners\Team\SendTeamNotification
 */
interface NotifiesTeam extends ShouldDispatchAfterCommit
{
    /**
     * Get the team the event happened on.
     */
    public function team(): Team;

    /**
     * Get the user who caused the event, who is left out of
     * the recipients, or null when nobody among them did.
     */
    public function causer(): ?User;

    /**
     * Get the email the event sends.
     */
    public function notification(): TeamNotification;
}
