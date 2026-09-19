<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Middleware;

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    /**
     * Specify the permission the route team requires, the way
     * Pennant's feature middleware specifies a feature.
     */
    public static function using(Permission $permission): string
    {
        return static::class.':'.$permission->value;
    }

    /**
     * Handle an incoming request, refusing a user whose effective
     * role on the route team does not grant the permission. It
     * runs after \App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership,
     * so the team is already bound and the user already belongs
     * to it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var \App\Modules\Core\Identity\Models\User|null $user */
        $user = $request->user();

        $team = $request->route('team');

        if (! $team instanceof Team || ! $user instanceof User) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_unless($user->hasTeamPermission($team, Permission::from($permission)), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
