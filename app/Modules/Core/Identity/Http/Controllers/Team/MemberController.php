<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Identity\Actions\Team\RemoveMember;
use App\Modules\Core\Identity\Http\Requests\Team\Member\IndexRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MemberController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        return Inertia::render('identity/teams/members/index', [
            'filters' => $request->validated('filter', []),
            'members' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->users(), $request)
                    ->allowedFilters(...$request->filters())
                    ->orderBy('name')
                    ->paginate(10)
                    ->withQueryString(),
            ),
        ]);
    }

    /**
     * Remove the specified resource from the team.
     */
    public function destroy(Team $team, User $user, #[CurrentUser] User $currentUser, RemoveMember $removeMember): RedirectResponse
    {
        // A team is never left without members.
        abort_if($team->users()->count() === 1, HttpResponse::HTTP_CONFLICT);

        // Whether the member removes itself from the team.
        $isLeaving = $user->is($currentUser);

        // Remove the member from the team.
        $next = $removeMember
            ->for($team)
            ->using($user);

        // Show a success message.
        Toast::make(trans($isLeaving ? 'You left the team' : 'Member removed'))
            ->flash();

        // A member that stays in the team goes back to the listing.
        if (! $isLeaving) {
            return back();
        }

        // The member that left no longer has access to the team, so it
        // lands on another one of its teams, or creates a new one.
        return $next instanceof Team
            ? to_route('finance.teams.dashboard.index', ['team' => $next->getRouteKey()])
            : to_route('identity.teams.create');
    }
}
