<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers;

use App\Modules\Core\Identity\Actions\Team\DeleteTeam;
use App\Modules\Core\Identity\Http\Requests\Team\DestroyRequest;
use App\Modules\Core\Identity\Http\Requests\Team\UpdateRequest;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Laravel\Paddle\Exceptions\PaddleException;

class TeamController
{
    /**
     * Display the team settings.
     */
    public function index(Team $team): Response
    {
        return Inertia::render('identity/teams/index', [
            // Deleting a subscribed team cancels its subscription,
            // so the page warns about it.
            'subscribed' => $team->subscribed(),
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

    /**
     * Show the confirmation for deleting the team.
     */
    public function delete(Team $team): Modal
    {
        return Inertia::modal('identity/teams/delete', [
            'subscribed' => $team->subscribed(),
            'team' => $team,
        ])->baseRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Remove the team from storage.
     */
    public function destroy(Team $team, DestroyRequest $request, #[CurrentUser] User $user, DeleteTeam $deleteTeam): RedirectResponse
    {
        // Delete the team and everything it owns, cancelling its
        // subscription. When Paddle refuses, nothing is deleted.
        try {
            $next = $deleteTeam
                ->by($user)
                ->using($team);
        } catch (PaddleException $exception) {
            report($exception);

            Toast::make(trans('The subscription could not be cancelled. Try again.'))
                ->flash();

            return back();
        }

        // Show a success message.
        Toast::make(trans('Team deleted'))
            ->flash();

        // The team is gone, so the user lands on another one of
        // its teams, or creates a new one.
        return $next instanceof Team
            ? to_route('finance.teams.dashboard.index', ['team' => $next->getRouteKey()])
            : to_route('identity.teams.create');
    }
}
