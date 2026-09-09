<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers;

use App\Modules\Core\Identity\Http\Requests\Team\UpdateRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Objects\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class TeamController
{
    /**
     * Display the team settings.
     */
    public function index(Team $team): Response
    {
        return Inertia::render('identity/teams/index', [
            'team' => $team,
        ]);
    }

    /**
     * Show the form for editing the team.
     */
    public function edit(Team $team): Modal
    {
        return Inertia::modal('identity/teams/edit', [
            'team' => $team,
        ])->baseRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Update the team in storage.
     */
    public function update(Team $team, UpdateRequest $request): RedirectResponse
    {
        // Update the team with the validated data.
        $team->update(['name' => $request->validated('name')]);

        // Show a success message.
        Toast::make(trans('Saved'))
            ->flash();

        // Renaming the team regenerates its slug, so the previous
        // URL no longer resolves. Land on the new one.
        return to_route('identity.teams.settings.index', ['team' => $team->getRouteKey()]);
    }
}
