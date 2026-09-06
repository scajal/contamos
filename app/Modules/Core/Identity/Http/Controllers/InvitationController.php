<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Actions\User\AcceptInvitation;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController
{
    /**
     * Display the invitation to the invitee.
     */
    public function show(TeamInvitation $invitation, #[CurrentUser] User $user): Response
    {
        return Inertia::render('identity/invitations/show', [
            'invitation' => $invitation->load(['inviter', 'team']),
            'isForCurrentUser' => $invitation->isFor($user->email),
        ]);
    }

    /**
     * Accept the invitation and join the team.
     */
    public function accept(TeamInvitation $invitation, #[CurrentUser] User $user, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        // If the invitation can no longer be accepted by this user,
        // show it again so the page explains why.
        if (! $invitation->isPending() || ! $invitation->isFor($user->email)) {
            return to_route('identity.invitations.show', ['invitation' => $invitation]);
        }

        // A user already in a Free-plan team cannot join another
        // Free-plan team; Pro teams are always joinable. A user who
        // already belongs to this team is just re-confirming, not
        // joining a new one, so the limit does not apply.
        if (! $user->belongsToTeam($invitation->team) && $invitation->team->plan() === Plan::Free && ! $user->canJoinFreeTeam()) {
            Toast::make(trans('You already belong to a free team. Upgrade that team or ask this team to upgrade.'))
                ->flash();

            return to_route('identity.invitations.show', ['invitation' => $invitation]);
        }

        // Join the team and switch to it.
        $team = $acceptInvitation
            ->for($user)
            ->using($invitation);

        // Show a message to the user.
        Toast::make(trans('Welcome to :team', ['team' => $team->name]))
            ->flash();

        return to_route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]);
    }
}
