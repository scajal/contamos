<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Identity\Actions\Team\RemoveMember;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Http\Requests\Team\Member\IndexRequest;
use App\Modules\Core\Identity\Http\Requests\Team\Member\UpdateRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
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
                    ->withQueryString()
                    ->through(fn (User $member): User => $this->withEffectiveRole($team, $member)),
            ),
        ]);
    }

    /**
     * Show the form for changing the role of a member.
     */
    public function edit(Team $team, User $user, #[CurrentUser] User $currentUser): Modal
    {
        $this->assertRoleIsEditable($team, $user, $currentUser);

        return Inertia::modal('identity/teams/members/role', [
            'member' => $user,
            'roles' => Role::options($team->assignableRoles()),
        ])->baseRoute('identity.teams.members.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the role of a member.
     */
    public function update(Team $team, User $user, UpdateRequest $request, #[CurrentUser] User $currentUser): RedirectResponse
    {
        $this->assertRoleIsEditable($team, $user, $currentUser);

        // Store the role the request asked for. The effective role
        // follows the team's plan, the stored one does not.
        $team->users()->updateExistingPivot($user->getKey(), $request->validated());

        // Show a success message.
        Toast::make(trans('Role updated'))
            ->flash();

        return back();
    }

    /**
     * Refuse editing a role that no request may change: the owner's,
     * because a team keeps exactly one owner, and the acting user's
     * own, because nobody promotes or demotes themself.
     */
    protected function assertRoleIsEditable(Team $team, User $user, User $currentUser): void
    {
        abort_if($user->roleOn($team) === Role::Owner, HttpResponse::HTTP_FORBIDDEN);

        abort_if($user->is($currentUser), HttpResponse::HTTP_FORBIDDEN);
    }

    /**
     * Replace the stored role on the member's membership with the
     * role they effectively hold, so the listing shows what the
     * member may actually do under the team's plan.
     */
    protected function withEffectiveRole(Team $team, User $member): User
    {
        $member->membership?->setAttribute('role', $team->effectiveRole($member->membership->role));

        return $member;
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

        // Leaving is open to every member; removing somebody else
        // needs the permission for it.
        abort_if(! $isLeaving && ! $currentUser->hasTeamPermission($team, Permission::RemoveMember), HttpResponse::HTTP_FORBIDDEN);

        // The team is never left without its owner, so the owner is
        // neither removed nor allowed to leave.
        abort_if($user->roleOn($team) === Role::Owner, HttpResponse::HTTP_FORBIDDEN);

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
