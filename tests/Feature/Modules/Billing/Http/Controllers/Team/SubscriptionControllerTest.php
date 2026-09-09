<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Billing\Http\Controllers\Team\SubscriptionController.
 */
describe('upgrade', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('billing.teams.subscriptions.upgrade', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('renders the upgrade modal with every feature and its limits', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::AddAccounts->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('billing/teams/subscriptions/upgrade')
                ->where('feature', Feature::AddAccounts->value)
                ->where('plan', Plan::Free->value)
                ->has('features', count(Feature::cases()))
                ->where('features.0.feature', Feature::AddAccounts->value)
                ->where('features.0.label', Feature::AddAccounts->getLabel())
                ->where('features.0.free', 3)
                ->where('features.0.pro', null));
    });

    it('renders without a feature when none was blocked', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('billing/teams/subscriptions/upgrade')
                ->where('feature', null));
    });

    it('rejects an unknown feature', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => 'unknown',
            ]))
            ->assertRedirect()
            ->assertInvalid(['feature']);
    });
});
