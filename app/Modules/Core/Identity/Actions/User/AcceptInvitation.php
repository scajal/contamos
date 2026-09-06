<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\User;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    /**
     * The user who accepts the invitation.
     */
    public ?User $user = null;

    /**
     * Set the user who accepts the invitation.
     */
    public function for(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Accept the invitation, join the team and
     * switch the user to it.
     */
    public function using(TeamInvitation $invitation): Team
    {
        // If the user is not set, throw an exception.
        if (! $this->user instanceof User) {
            throw new Exception('An invitation cannot be accepted without a user.');
        }

        // Only open invitations sent to the user can be accepted.
        if (! $invitation->isPending()) {
            throw new Exception('The invitation is no longer pending.');
        }

        // Only invitations sent to the user can be accepted.
        if (! $invitation->isFor($this->user->email)) {
            throw new Exception('The invitation was sent to a different email address.');
        }

        $user = $this->user;

        return DB::transaction(function () use ($invitation, $user): Team {
            // Join the team, unless the user is already a member.
            if (! $user->belongsToTeam($invitation->team)) {
                $user->teams()->attach($invitation->team);
            }

            // Mark the invitation as accepted.
            $invitation->accept();

            // Switch the user to the team.
            $user->switchTeam($invitation->team);

            return $invitation->team;
        });
    }
}
