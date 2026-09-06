<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\Team;

use App\Modules\Core\Identity\Actions\Team\CreateInvitation;
use App\Modules\Core\Identity\Actions\Team\Invitation\RenewAndResendTeamInvitation;
use App\Modules\Core\Identity\Http\Requests\Team\Invitation\IndexRequest;
use App\Modules\Core\Identity\Http\Requests\Team\Invitation\StoreRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
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

class InvitationController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, IndexRequest $request): Response
    {
        return Inertia::render('identity/teams/invitations/index', [
            'filters' => $request->validated('filter', []),
            'invitations' => Inertia::defer(
                fn (): LengthAwarePaginator => QueryBuilder::for($team->invitations()->with('inviter'), $request)
                    ->allowedFilters(...$request->filters())
                    ->latest()
                    ->paginate(10)
                    ->withQueryString(),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Team $team): Modal
    {
        return Inertia::modal('identity/teams/invitations/create')
            ->baseRoute('identity.teams.invitations.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Team $team, StoreRequest $request, #[CurrentUser] User $user, CreateInvitation $createInvitation): RedirectResponse
    {
        // Create the invitation and email it to the invitee.
        $createInvitation
            ->for($team)
            ->by($user)
            ->using($request->safe(['email']));

        // Show a success message.
        Toast::make(trans('Invitation sent'))
            ->flash();

        return back();
    }

    /**
     * Renew the specified resource and email it again.
     */
    public function resend(Team $team, TeamInvitation $invitation, RenewAndResendTeamInvitation $renewAndResendTeamInvitation): RedirectResponse
    {
        // An accepted invitation cannot be sent again.
        abort_if($invitation->isAccepted(), HttpResponse::HTTP_CONFLICT);

        // Renew and re-send the invitation.
        $renewAndResendTeamInvitation->using($invitation);

        // Show a success message.
        Toast::make(trans('Invitation sent'))
            ->flash();

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        // An accepted invitation is kept as history.
        abort_if($invitation->isAccepted(), HttpResponse::HTTP_CONFLICT);

        // Delete the invitation.
        $invitation->delete();

        // Show a success message.
        Toast::make(trans('Deleted'))
            ->flash();

        return back();
    }
}
