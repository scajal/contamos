<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\Team\MemberController.
 */
describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('identity.teams.members.index', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders a deferred list of members with their membership', function (): void {
        [$user, $team] = actingMember();
        $user->update(['name' => 'Ada Lovelace']);
        $other = User::factory()->create(['name' => 'Grace Hopper']);
        $other->teams()->attach($team);

        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/members/index')
                ->has('user')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('members.data', 2)
                    ->where('members.data.0.name', 'Ada Lovelace')
                    ->where('members.data.0.membership.team_id', $team->getKey())
                    ->where('members.data.1.name', 'Grace Hopper')));
    });

    it('filters members by search', function (): void {
        [$user, $team] = actingMember();
        $user->update(['name' => 'Ada Lovelace']);
        $other = User::factory()->create(['name' => 'Grace Hopper']);
        $other->teams()->attach($team);

        $this->get(route('identity.teams.members.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['search' => 'Grace'],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.search', 'Grace')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('members.data', 1)
                    ->where('members.data.0.id', $other->getKey())));
    });

    it('rejects a search longer than 100 characters', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('identity.teams.members.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['search' => str_repeat('a', 101)],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.search']);
    });
});
