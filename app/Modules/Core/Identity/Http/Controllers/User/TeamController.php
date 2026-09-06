<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\User;

use App\Modules\Core\Identity\Actions\User\CreateTeam;
use App\Modules\Core\Identity\Http\Requests\User\Team\StoreRequest;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TeamController
{
    /**
     * Show a form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('identity/users/teams/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request, #[CurrentUser] User $user, CreateTeam $createTeam): RedirectResponse
    {
        $user->loadMissing('teams.subscriptions');

        if (isset($user->current_team_id)) {
            $user->loadMissing('currentTeam');
        }

        // A user may only belong to one Free-plan team at a
        // time; Pro teams never count against the limit.
        if (! $user->canJoinFreeTeam()) {
            abort_if(! $user->currentTeam, HttpResponse::HTTP_FORBIDDEN);

            Toast::make(trans('You already belong to a free team. Upgrade that team to create another.'))
                ->flash();

            return to_route('billing.teams.subscriptions.index', ['team' => $user->currentTeam->getRouteKey()]);
        }

        // Store the currency with the validated data.
        $team = $createTeam
            ->for($user)
            ->using($request->validated());

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        return to_route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]);
    }
}
