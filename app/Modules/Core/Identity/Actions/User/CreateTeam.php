<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Actions\User;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team for the provided user and
     * associate it to it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(User $user, array $attributes): Team
    {
        return DB::transaction(function () use ($attributes, $user): Team {
            // Create the team.
            $team = Team::query()->create($attributes);

            // Associate the team to the user.
            $user->teams()->attach($team);

            // Return the team.
            return $team;
        });
    }
}
