<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\User;

use App\Modules\Core\Identity\Actions\User\CreateTeam;
use App\Modules\Core\Identity\Http\Requests\User\Team\StoreRequest;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\AnalyticsEvent;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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
        // Store the currency with the validated data.
        $team = $createTeam
            ->for($user)
            ->using($request->validated());

        // Show a message to the user.
        Toast::make(trans('Saved'))
            ->flash();

        // Report the new team to Google Analytics.
        AnalyticsEvent::make('create_team')
            ->flash();

        return to_route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]);
    }
}
