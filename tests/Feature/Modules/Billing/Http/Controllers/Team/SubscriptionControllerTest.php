<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    it('previews the pro prices from paddle', function (): void {
        Cache::flush();
        [$user, $team] = actingMember();

        Http::fake([
            '*/pricing-preview' => Http::sequence()
                ->push(['data' => ['details' => ['line_items' => [[
                    'price' => ['id' => Plan::Pro->priceId(BillingCycle::Monthly)],
                    'formatted_totals' => ['total' => '$4.99'],
                    'totals' => ['total' => '499'],
                ]]]]])
                ->push(['data' => ['details' => ['line_items' => [[
                    'price' => ['id' => Plan::Pro->priceId(BillingCycle::Yearly)],
                    'formatted_totals' => ['total' => '$39.99'],
                    'totals' => ['total' => '3999'],
                ]]]]]),
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('prices.monthly.formatted', '$4.99')
                ->where('prices.monthly.amount', 499)
                ->where('prices.yearly.formatted', '$39.99')
                ->where('prices.yearly.amount', 3999));
    });

    it('renders without prices when paddle cannot be reached', function (): void {
        Cache::flush();
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('prices.monthly.formatted', null)
                ->where('prices.monthly.amount', null)
                ->where('prices.yearly.formatted', null));
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

describe('subscribe', function (): void {
    it('renders the checkout modal with the paddle settings', function (): void {
        [$user, $team] = actingMember();
        config()->set('cashier.client_side_token', 'test_token');
        config()->set('cashier.sandbox', true);

        Http::fake([
            '*/customers*' => Http::response(['data' => [[
                'id' => 'ctm_test',
                'name' => $team->name,
                'email' => $user->email,
            ]]]),
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.subscribe', [
                'team' => $team->getRouteKey(),
                'cycle' => BillingCycle::Monthly->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('billing/teams/subscriptions/subscribe')
                ->has('checkout')
                ->where('paddle.token', 'test_token')
                ->where('paddle.sandbox', true));
    });
});
