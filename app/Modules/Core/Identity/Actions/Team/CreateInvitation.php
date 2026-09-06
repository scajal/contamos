<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\Team;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification;
use Exception;
use Illuminate\Support\Facades\Notification;

class CreateInvitation
{
    /**
     * The team for which the invitation will be created.
     */
    public ?Team $team = null;

    /**
     * The user who sends the invitation.
     */
    public ?User $user = null;

    /**
     * Set the team for which the invitation will be created.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Set the user who sends the invitation.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Create an invitation for the team and email
     * it to the invitee.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(array $attributes): TeamInvitation
    {
        // If the team or the inviter is not set, throw an exception.
        if (! $this->team instanceof \App\Modules\Core\Identity\Models\Team) {
            throw new Exception('An invitation cannot be created without a team.');
        }

        if (! $this->user instanceof \App\Modules\Core\Identity\Models\User) {
            throw new Exception('An invitation cannot be created without an inviter.');
        }

        // Create the invitation for the team.
        $invitation = $this->team->invitations()->make($attributes);
        $invitation->inviter()->associate($this->user);
        $invitation->save();

        // Email the invitation to the invitee, who may not
        // have an account yet.
        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }
}
