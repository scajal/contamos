<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Paddle\Exceptions\PaddleException;

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
            ->patch(route('identity.teams.settings.update', ['team' => 'acme-family']), [
                'name' => 'Cajal Household',
                'slug' => 'cajal-household',
            ])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => 'cajal-household']);

        expect($team->fresh()?->name)->toBe('Cajal Household')
            ->and($team->fresh()?->slug)->toBe('cajal-household');

        assertFlashedToast(trans('Saved'));
    });

    it('requires a name', function (): void {
        [$user, $team] = actingMember();
        $name = $team->name;

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), [
                'name' => '',
                'slug' => $team->slug,
            ])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()])
            ->assertInvalid(['name']);

        expect($team->fresh()?->name)->toBe($name);
    });

    it('requires a handle', function (): void {
        [$user, $team] = actingMember();
        $slug = $team->slug;

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), [
                'name' => $team->name,
                'slug' => '',
            ])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()])
            ->assertInvalid(['slug']);

        expect($team->fresh()?->slug)->toBe($slug);
    });

    it('rejects a name longer than 255 characters', function (): void {
        [$user, $team] = actingMember();

        $this->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), [
            'name' => str_repeat('a', 256),
            'slug' => $team->slug,
        ])
            ->assertRedirect()
            ->assertInvalid(['name']);
    });

    it('rejects a handle that is already taken', function (): void {
        [$user, $team] = actingMember();
        $slug = $team->slug;
        Team::factory()->create(['slug' => 'taken']);

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), [
                'name' => $team->name,
                'slug' => 'taken',
            ])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()])
            ->assertInvalid(['slug']);

        expect($team->fresh()?->slug)->toBe($slug);
    });
});

describe('delete', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.delete', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.delete', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders the confirmation modal with the team', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.delete', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/delete')
                ->where('subscribed', false)
                ->where('team.id', $team->getKey())
                ->where('team.slug', $team->slug));
    });

    it('warns about the subscription of a subscribed team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.settings.delete', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('subscribed', true));
    });
});

describe('destroy', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => $team->slug])
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->delete(route('identity.teams.settings.destroy', ['team' => $other->getRouteKey()]), ['slug' => $other->slug])
            ->assertForbidden();

        expect(Team::query()->whereKey($other->getKey())->exists())->toBeTrue();
    });

    it('requires the typed handle to match the team', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => 'something-else'])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()])
            ->assertInvalid(['slug' => trans('The handle does not match the team.')]);

        expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue();
    });

    it('requires a handle', function (): void {
        [$user, $team] = actingMember();

        $this->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => ''])
            ->assertRedirect()
            ->assertInvalid(['slug']);

        expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue();
    });

    it('cancels the subscription on paddle before deleting a subscribed team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        Http::fake([
            '*/subscriptions/*/cancel' => Http::response(['data' => [
                'status' => \Laravel\Paddle\Subscription::STATUS_CANCELED,
                'canceled_at' => '2026-09-09T12:00:00Z',
            ]]),
        ]);

        $this->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => $team->slug])
            ->assertRedirectToRoute('identity.teams.create');

        expect(Team::query()->whereKey($team->getKey())->exists())->toBeFalse();

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/cancel')
            && $request['effective_from'] === 'immediately');
    });

    it('keeps a subscribed team when paddle refuses the cancellation', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        Http::fake([
            '*/subscriptions/*/cancel' => Http::response(['error' => ['detail' => 'subscription is locked']], 400),
        ]);

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => $team->slug])
            ->assertRedirectToRoute('identity.teams.settings.index', ['team' => $team->getRouteKey()]);

        expect(Team::query()->whereKey($team->getKey())->exists())->toBeTrue()
            ->and($team->fresh()?->subscribed())->toBeTrue();

        assertFlashedToast(trans('The subscription could not be cancelled. Try again.'));
        Exceptions::assertReported(PaddleException::class);
    });

    it('deletes the team and lands on another team of the user', function (): void {
        [$user, $team] = actingMember();
        $other = Team::factory()->create();
        $user->teams()->attach($other);

        $this->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => $team->slug])
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $other->getRouteKey()]);

        expect(Team::query()->whereKey($team->getKey())->exists())->toBeFalse()
            ->and($user->fresh()?->current_team_id)->toBe($other->getKey());

        assertFlashedToast(trans('Team deleted'));
    });

    it('deletes the last team of the user and lands on the team creation page', function (): void {
        [$user, $team] = actingMember();

        $this->delete(route('identity.teams.settings.destroy', ['team' => $team->getRouteKey()]), ['slug' => $team->slug])
            ->assertRedirectToRoute('identity.teams.create');

        expect(Team::query()->whereKey($team->getKey())->exists())->toBeFalse()
            ->and($user->fresh()?->current_team_id)->toBeNull();
    });
});
