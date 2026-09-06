<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Paddle\Cashier;

/**
 * Feature tests for \App\Modules\Billing\Http\Controllers\Team\SubscriptionController.
 */

/**
 * Seed an active subscription for the given team.
 */
function seedActiveSubscription(Team $team, string $priceId = 'pri_month'): void
{
    $team->subscriptions()->create([
        'type' => 'default',
        'paddle_id' => 'sub_test',
        'status' => 'active',
    ])->items()->create([
        'product_id' => 'pro_test',
        'price_id' => $priceId,
        'status' => 'active',
        'quantity' => 1,
    ]);
}

describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('billing.teams.subscriptions.index', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders checkouts and the free plan for a team with no subscription', function (): void {
        Cashier::fake();
        config()->set('billing.plans.pro.prices.month', 'pri_month');
        config()->set('billing.plans.pro.prices.year', 'pri_year');
        [$user, $team] = actingMember();
        $team->customer()->create(['name' => $team->name, 'email' => $user->email, 'paddle_id' => 'cus_test']);

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('billing/teams/subscriptions/index')
                ->where('plan', 'free')
                ->where('subscription', null)
                ->has('checkouts.month')
                ->has('checkouts.year'));
    });

    it('renders the subscription and no checkouts for a subscribed team', function (): void {
        config()->set('billing.plans.pro.prices.month', 'pri_month');
        [$user, $team] = actingMember();
        seedActiveSubscription($team);

        Illuminate\Support\Facades\Http::fake([
            '*/subscriptions/sub_test*' => Illuminate\Support\Facades\Http::response(['data' => [
                'next_transaction' => [
                    'details' => ['totals' => ['grand_total' => '999', 'currency_code' => 'USD']],
                    'billing_period' => ['starts_at' => now()->toIso8601String()],
                ],
            ]]),
        ]);

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('plan', 'pro')
                ->where('checkouts', null)
                ->where('subscription.interval', 'month')
                ->where('subscription.status', 'active')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data')));
    });
});

describe('update', function (): void {
    it('swaps the subscription interval', function (): void {
        config()->set('billing.plans.pro.prices.month', 'pri_month');
        config()->set('billing.plans.pro.prices.year', 'pri_year');
        [$user, $team] = actingMember();
        seedActiveSubscription($team);

        Cashier::fake()->response('subscriptions/sub_test', [
            'id' => 'sub_test',
            'status' => 'active',
            'items' => [
                ['price' => ['id' => 'pri_year', 'product_id' => 'pro_test'], 'status' => 'active', 'quantity' => 1],
            ],
        ]);

        $this->from(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('billing.teams.subscriptions.update', ['team' => $team->getRouteKey()]), ['interval' => 'year'])
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($team->subscription()?->fresh()?->hasPrice('pri_year'))->toBeTrue();
    });

    it('rejects an invalid interval', function (): void {
        [$user, $team] = actingMember();
        seedActiveSubscription($team);

        $this->from(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('billing.teams.subscriptions.update', ['team' => $team->getRouteKey()]), ['interval' => 'week'])
            ->assertRedirect()
            ->assertInvalid(['interval']);
    });

    it('returns 404 when the team has no subscription', function (): void {
        [$user, $team] = actingMember();

        $this->patch(route('billing.teams.subscriptions.update', ['team' => $team->getRouteKey()]), ['interval' => 'year'])
            ->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('cancels the subscription at the end of the billing period', function (): void {
        [$user, $team] = actingMember();
        seedActiveSubscription($team);

        Cashier::fake()->response('subscriptions/sub_test/cancel', [
            'status' => 'active',
            'scheduled_change' => ['action' => 'cancel', 'effective_at' => now()->addMonth()->toIso8601String()],
        ]);

        $this->from(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->delete(route('billing.teams.subscriptions.destroy', ['team' => $team->getRouteKey()]))
            ->assertRedirect();

        assertFlashedToast(trans('Your subscription will end at the end of the billing period'));
        expect($team->subscription()?->fresh()?->ends_at)->not->toBeNull();
    });
});

describe('resume', function (): void {
    it('stops a scheduled cancelation', function (): void {
        [$user, $team] = actingMember();
        seedActiveSubscription($team);
        $team->subscription()?->forceFill(['ends_at' => now()->addMonth()])->save();

        Cashier::fake()->response('subscriptions/sub_test', [
            'id' => 'sub_test',
            'status' => 'active',
        ]);

        $this->from(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->patch(route('billing.teams.subscriptions.resume', ['team' => $team->getRouteKey()]))
            ->assertRedirect();

        assertFlashedToast(trans('Subscription resumed'));
        expect($team->subscription()?->fresh()?->ends_at)->toBeNull();
    });
});
