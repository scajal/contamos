<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\TeamController.
 */
describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('identity.teams.settings.index', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders the team details', function (): void {
        [$user, $team] = actingMember();
        $team->update(['name' => 'Acme Family']);

        $this->get(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/index')
                ->has('user')
                ->where('team.id', $team->getKey())
                ->where('team.name', 'Acme Family')
                ->where('team.slug', 'acme-family'));
    });
});

describe('edit', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.edit', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.edit', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders the edit modal with the team', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.edit', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/edit')
                ->where('team.id', $team->getKey())
                ->where('team.name', $team->name));
    });
});

describe('update', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), ['name' => 'Acme'])
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create(['name' => 'Other']);

        $this->patch(route('identity.teams.settings.update', ['team' => $other->getRouteKey()]), ['name' => 'Acme'])
            ->assertForbidden();

        expect($other->fresh()?->name)->toBe('Other');
    });

    it('renames the team and lands on the settings of the new slug', function (): void {
        [$user, $team] = actingMember();
        $team->update(['name' => 'Acme Family']);

        $this->from(route('identity.teams.settings.index', ['team' => 'acme-family']))
            ->patch(route('identity.teams.settings.update', ['team' => 'acme-family']), ['name' => 'Cajal Household'])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => 'cajal-household']);

        expect($team->fresh()?->name)->toBe('Cajal Household')
            ->and($team->fresh()?->slug)->toBe('cajal-household');

        assertFlashedToast(trans('Saved'));
    });

    it('requires a name', function (): void {
        [$user, $team] = actingMember();
        $name = $team->name;

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), ['name' => ''])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()])
            ->assertInvalid(['name']);

        expect($team->fresh()?->name)->toBe($name);
    });

    it('rejects a name longer than 255 characters', function (): void {
        [$user, $team] = actingMember();

        $this->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), ['name' => str_repeat('a', 256)])
            ->assertRedirect()
            ->assertInvalid(['name']);
    });
});
