<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\User\TeamController.
 */
describe('create', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('identity.teams.create'))
            ->assertRedirectToRoute('login');
    });

    it('renders the team creation page', function (): void {
        $this->actingAs(User::factory()->create());

        $this->get(route('identity.teams.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/users/teams/create')
                ->has('user'));
    });
});

describe('store', function (): void {
    it('redirects guests to login', function (): void {
        $this->post(route('identity.teams.store'), ['name' => 'Acme'])
            ->assertRedirectToRoute('login');
    });

    it('creates a team, attaches the user, and redirects to the dashboard', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->from(route('identity.teams.create'))
            ->post(route('identity.teams.store'), ['name' => 'Acme'])
            ->assertRedirectToRoute('finance.teams.dashboard.index', [
                'team' => Team::query()->sole()->getRouteKey(),
            ]);

        $team = Team::query()->sole();

        expect($user->fresh()?->belongsToTeam($team))->toBeTrue();
        assertFlashedToast(trans('Saved'));
        $this->assertDatabaseHas($team->getTable(), [
            'name' => 'Acme',
        ]);
    });

    it('rejects a missing name', function (): void {
        $this->actingAs(User::factory()->create());

        $this->from(route('identity.teams.create'))
            ->post(route('identity.teams.store'), [])
            ->assertRedirect()
            ->assertInvalid([
                'name' => trans('validation.required', ['attribute' => trans('validation.attributes.name')]),
            ]);
    });

    it('rejects a name longer than 255 characters', function (): void {
        $this->actingAs(User::factory()->create());

        $this->from(route('identity.teams.create'))
            ->post(route('identity.teams.store'), ['name' => str_repeat('a', 256)])
            ->assertRedirect()
            ->assertInvalid([
                'name' => trans('validation.max.string', [
                    'attribute' => trans('validation.attributes.name'),
                    'max' => 255,
                ]),
            ]);
    });

    it('blocks a user already in a free team from creating another', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.create'))
            ->post(route('identity.teams.store'), ['name' => 'Second Team'])
            ->assertRedirectToRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);

        assertFlashedToast(trans('You already belong to a free team. Upgrade that team to create another.'));
        expect(Team::query()->count())->toBe(1);
    });

    it('lets a user whose only team is pro create another team', function (): void {
        \Laravel\Paddle\Cashier::fake();
        [$user, $team] = actingMember();
        $team->subscriptions()->create(['type' => 'default', 'paddle_id' => 'sub_test', 'status' => 'active'])
            ->items()->create(['product_id' => 'pro_test', 'price_id' => 'pri_month', 'status' => 'active', 'quantity' => 1]);

        $this->from(route('identity.teams.create'))
            ->post(route('identity.teams.store'), ['name' => 'Second Team'])
            ->assertRedirectToRoute('finance.teams.dashboard.index', [
                'team' => Team::query()->where('name', 'Second Team')->sole()->getRouteKey(),
            ]);

        expect(Team::query()->count())->toBe(2);
    });
});
