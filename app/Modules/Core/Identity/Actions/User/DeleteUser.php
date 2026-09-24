<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\User;

use App\Modules\Authentication\Support\AuthKitUserDeletion;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Actions\Team\DeleteTeam;
use App\Modules\Core\Identity\Events\Team\MemberLeft;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature as Pennant;

class DeleteUser
{
    /**
     * Create a new instance of the class.
     */
    public function __construct(
        protected AuthKitUserDeletion $authKitUserDeletion,
        protected DeleteTeam $deleteTeam
    ) {}

    /**
     * Delete the user, the teams where they were the only member,
     * and their identity at WorkOS. Teams they shared with others
     * survive with the membership removed.
     *
     * @throws \Laravel\Paddle\Exceptions\PaddleException When Paddle refuses a cancellation.
     * @throws \WorkOS\Exception\WorkOSException When WorkOS refuses the deletion.
     */
    public function using(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // Teams the user shares with nobody go away with the
            // account, through the team Action so the Paddle
            // cancellation is not duplicated here. A refusal
            // aborts before anything else is touched.
            foreach ($user->teams()->solo()->get() as $team) {
                $this->deleteTeam
                    ->by($user)
                    ->using($team);
            }

            // Every team the user shares reads the deletion as the
            // user leaving, which its Owner and Admins hear about
            // once the deletion commits.
            foreach ($user->teams()->get() as $team) {
                MemberLeft::dispatch($team, $user->name);
            }

            // The identity at WorkOS goes next, so a sign-in with
            // the same email cannot recreate the account. A user
            // without an identifier there has nothing to delete.
            if ($user->external_id !== null) {
                $this->authKitUserDeletion->deleteUsing($user->external_id);
            }

            // Every device holding a session is logged out.
            DB::table(config()->string('session.table'))
                ->where($user->getForeignKey(), $user->getKey())
                ->delete();

            // The feature flag rows scoped to the user are dropped,
            // so the table does not keep rows nothing resolves.
            Pennant::for($user)->forget(array_column(Feature::cases(), 'value'));

            // Memberships in shared teams and invitations the user
            // sent cascade from the row; transactions and quotes
            // keep theirs with the author reference nulled.
            $user->delete();
        });
    }
}
