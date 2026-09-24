<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\Team;

use App\Modules\Core\Identity\Events\Team\MemberLeft;
use App\Modules\Core\Identity\Events\Team\MemberRemoved;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    /**
     * The team from which the member will be removed.
     */
    public ?Team $team = null;

    /**
     * The user removing the member, who is the member
     * themself when they leave.
     */
    public ?User $user = null;

    /**
     * Set the user removing the member.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team from which the member will be removed.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Remove the member from the team and move it to another
     * team when the removed one was its current team.
     */
    public function using(User $member): ?Team
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A member cannot be removed without a team.');
        }

        // If the user removing the member is not set, throw an
        // exception.
        if (! $this->user instanceof User) {
            throw new Exception('A member cannot be removed without the user removing them.');
        }

        $team = $this->team;
        $user = $this->user;

        return DB::transaction(function () use ($member, $team, $user): ?Team {
            // Remember the stored role the member carried, since
            // the membership row is about to go.
            $role = $member->teams()
                ->whereKey($team->getKey())
                ->first()?->membership?->role;

            // Leave the team.
            $member->teams()->detach($team);

            // Record the member leaving, which no model change of
            // its own would show.
            TeamActivity::record($member, 'member_removed', [
                'old' => ['role' => $role?->value],
            ], $team);

            // Tell the Owner and Admins, once the removal commits,
            // whether the member left or somebody removed them.
            if ($user->is($member)) {
                MemberLeft::dispatch($team, $member->name);
            } else {
                MemberRemoved::dispatch($team, $member->name, $user);
            }

            // Keep the current team of the member untouched when it
            // is not the team it was removed from.
            if ($member->current_team_id !== $team->getKey()) {
                return $member->currentTeam;
            }

            // Move the member to any other team it still belongs to.
            $next = $member->teams()->first();

            if ($next instanceof Team) {
                $member->currentTeam()
                    ->associate($next)
                    ->save();
            } else {
                $member->currentTeam()
                    ->dissociate()
                    ->save();
            }

            return $next;
        });
    }
}
