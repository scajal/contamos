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

describe('destroy', function (): void {
    it('redirects guests to login', function (): void {
        [$user, $team] = memberWithTeam();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        [$other, $team] = memberWithTeam();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertForbidden();
    });

    it('does not find a user who is not a member of the team', function (): void {
        [$user, $team] = actingMember();
        User::factory()->create()->teams()->attach($team);
        $stranger = User::factory()->create();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $stranger->getKey()]))
            ->assertNotFound();
    });

    it('removes another member of the team', function (): void {
        [$user, $team] = actingMember();
        $other = User::factory()->create();
        $other->teams()->attach($team);
        $other->switchTeam($team);

        $this->from(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertRedirectToRoute('identity.teams.members.index', ['team' => $team->getRouteKey()]);

        expect($other->fresh()?->belongsToTeam($team))->toBeFalse()
            ->and($other->fresh()?->current_team_id)->toBeNull()
            ->and($user->fresh()?->belongsToTeam($team))->toBeTrue();

        assertFlashedToast(trans('Member removed'));
    });

    it('conflicts when the member is the last one of the team', function (): void {
        [$user, $team] = actingMember();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertConflict();

        expect($user->fresh()?->belongsToTeam($team))->toBeTrue();
    });

    it('leaves the team and lands on another team of the member', function (): void {
        [$user, $team] = actingMember();
        User::factory()->create()->teams()->attach($team);
        $other = Team::factory()->create();
        $user->teams()->attach($other);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $other->getRouteKey()]);

        expect($user->fresh()?->belongsToTeam($team))->toBeFalse()
            ->and($user->fresh()?->current_team_id)->toBe($other->getKey());

        assertFlashedToast(trans('You left the team'));
    });

    it('leaves the last team of the member and lands on the team creation page', function (): void {
        [$user, $team] = actingMember();
        User::factory()->create()->teams()->attach($team);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirectToRoute('identity.teams.create');

        expect($user->fresh()?->current_team_id)->toBeNull();
    });
});
