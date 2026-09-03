<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\DashboardController
 * and \App\Modules\Core\Identity\Http\Middleware\EnsureTeamMembership.
 */
it('redirects guests to login', function (): void {
    $team = Team::factory()->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getKey()]))
        ->assertRedirectToRoute('login');
});

it('renders the dashboard for a team member', function (): void {
    [$user, $team] = actingMember();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->component('welcome')
            ->has('user')
            ->where('user.id', $user->getKey()));
});

it('returns 404 when the team does not exist', function (): void {
    actingMember();

    $this->get(route('finance.teams.dashboard.index', ['team' => (string) Str::ulid()]))
        ->assertNotFound();
});

it('redirects a user with no teams to team creation', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $this->actingAs($user);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getKey()]))
        ->assertRedirectToRoute('identity.teams.create');
});

it('forbids a user who belongs to another team', function (): void {
    actingMember();
    $foreignTeam = Team::factory()->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $foreignTeam->getKey()]))
        ->assertForbidden();
});

it('switches the current team when the route team differs', function (): void {
    [$user, $currentTeam] = actingMember();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam);

    expect($user->fresh()?->current_team_id)->toBe($currentTeam->getKey());

    $this->get(route('finance.teams.dashboard.index', ['team' => $otherTeam->getKey()]))
        ->assertOk();

    expect($user->fresh()?->current_team_id)->toBe($otherTeam->getKey());
});
