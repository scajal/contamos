<?php

namespace App\Modules\Core\Identity\Http\Middleware;

use App\Modules\Core\Identity\Models\Team;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the current user.
        /** @var \App\Modules\Core\Identity\Models\User|null $user */
        $user = $request->user();

        // Get the current team.
        $team = $this->team($request->route('team'));

        if (! $team || ! $user) {
            abort(Response::HTTP_NOT_FOUND);
        }

        // If the user is not a member of the team, return a 403 response.
        if (! $user->belongsToTeam($team)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // If the route team is not the current user's team,
        // switch to the team.
        if ($team->getKey() !== $user->currentTeam?->getKey()) {
            $user->switchTeam($team);
        }

        return $next($request);
    }

    /**
     * Get the current team from the
     * request.
     */
    protected function team(#[RouteParameter('team')] mixed $team): ?Team
    {
        if (is_string($team)) {
            $team = Team::query()
                ->where(new Team()->getRouteKeyName(), $team)
                ->first();
        }

        return $team;
    }
}
