<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\Team;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class DeleteTeam
{
    /**
     * The user deleting the team.
     */
    public ?User $user = null;

    /**
     * Set the user deleting the team.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Delete the team with everything it owns, cancelling its Paddle
     * subscription and moving every member whose current team it
     * was to another of their teams. Returns the team the acting
     * user lands on, if any.
     *
     * @throws \Laravel\Paddle\Exceptions\PaddleException When Paddle refuses the cancellation.
     */
    public function using(Team $team): ?Team
    {
        // If the user is not set, throw an exception.
        if (! $this->user instanceof User) {
            throw new Exception('A team cannot be deleted without a user.');
        }

        $user = $this->user;

        return DB::transaction(function () use ($team, $user): ?Team {
            // A live subscription is cancelled on Paddle first, so a
            // refusal leaves the team untouched.
            if (($subscription = $team->subscription()) instanceof Subscription && $subscription->valid()) {
                $subscription->cancelNow();
            }

            // Members on this team move to another of their teams
            // before it goes away. The others keep theirs.
            $team->users()
                ->where('current_team_id', $team->getKey())
                ->each(function (User $member) use ($team): void {
                    // Get the next team the member is on.
                    $next = $member->teams()
                        ->whereKeyNot($team->getKey())
                        ->first();

                    // If the member is on another team, set it as their
                    // current team. Otherwise, dissociate them from the team.
                    if ($next instanceof Team) {
                        $member->currentTeam()->associate($next)->save();
                    } else {
                        $member->currentTeam()->dissociate()->save();
                    }
                });

            // Billing rows are not constrained to the team, so they
            // are removed by hand.
            foreach ($team->subscriptions()->cursor() as $subscription) {
                if ($subscription instanceof Subscription) {
                    $subscription->items()->delete();
                    $subscription->delete();
                }
            }

            // The team's customer is deleted.
            $team->customer()->delete();

            // Memberships, accounts, transactions, tags, and
            // invitations cascade from the team.
            $team->delete();

            // Return the user's next team, if any.
            return $user->fresh()?->currentTeam;
        });
    }
}
