<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\User;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * The user for which the team will be created.
     */
    public ?User $user = null;

    /**
     * Set the user for which the team will be created.
     */
    public function for(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Create a new team for the provided user and
     * associate it to it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(array $attributes): Team
    {
        return DB::transaction(function () use ($attributes): Team {
            // Create the team.
            $team = Team::query()->create($attributes);

            // Associate the team to the user, who owns the team
            // they created.
            if ($this->user instanceof User) {
                $this->user->teams()->attach($team, ['role' => Role::Owner]);
            }

            // Return the team.
            return $team;
        });
    }
}
