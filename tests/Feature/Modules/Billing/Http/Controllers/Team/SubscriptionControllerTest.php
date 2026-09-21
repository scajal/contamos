<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Billing\Http\Controllers\Team\SubscriptionController.
 */
/**
 * Record a billed transaction for the team without calling Paddle.
 *
 * @param  array<string, mixed>  $overrides
 */
function billingTransaction(Team $team, array $overrides = []): \App\Modules\Billing\Models\Transaction
{
    /** @var \App\Modules\Billing\Models\Transaction $transaction */
    $transaction = $team->billingTransactions()->create([
        'billed_at' => CarbonImmutable::parse('2026-09-01 10:00:00'),
        'currency' => 'USD',
        'invoice_number' => 'INV-1',
        'paddle_id' => 'txn_'.fake()->unique()->lexify('??????'),
        'status' => 'completed',
        'tax' => '0.00',
        'total' => '4.99',
        ...$overrides,
    ]);

    return $transaction;
}

describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('renders a free team with no renewal', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('billing/teams/subscriptions/index')
                ->where('activating', false)
                ->where('plan.name', Plan::Free->value)
                ->where('plan.status', 'free')
                ->where('plan.cycle', null)
                ->has('plan.limits', 4)
                ->where('plan.limits.0.feature', Feature::AddAccounts->value)
                ->where('plan.limits.0.usage', 0)
                ->where('plan.limits.0.free', 3)
                ->where('plan.limits.0.pro', null)
                ->where('plan.limits.1.feature', Feature::AddMembers->value)
                ->where('plan.limits.1.usage', 1)
                ->where('plan.limits.2.feature', Feature::AssignRoles->value)
                ->where('plan.limits.2.usage', null)
                ->where('plan.limits.2.free', false)
                ->where('plan.limits.3.feature', Feature::ViewHistory->value)
                ->where('plan.limits.3.usage', 1)
                ->where('plan.limits.3.free', 6)
                ->where('plan.limits.3.pro', null)
                ->where('plan.limits.2.pro', true)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('renewal', null)
                    ->has('transactions.data', 0)));
    });

    it('reports usage above the free limits for a downgraded team', function (): void {
        [$user, $team] = actingMember();
        Account::factory()->count(4)->active()->for($team)->create();
        $team->users()->attach(User::factory()->count(2)->create(), ['role' => Role::Admin]);

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('plan.limits.0.usage', 4)
                ->where('plan.limits.0.free', 3)
                ->where('plan.limits.1.usage', 3)
                ->where('plan.limits.1.free', 2));
    });

    it('renders an active pro team with its cycle and next payment', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        billingTransaction($team);

        Http::fake([
            '*/subscriptions/*' => Http::response(['data' => ['next_transaction' => [
                'billing_period' => ['starts_at' => '2026-10-08T10:00:00Z'],
                'details' => ['totals' => ['grand_total' => '499', 'currency_code' => 'USD']],
            ]]]),
        ]);

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey(), 'activating' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('activating', true)
                ->where('plan.name', Plan::Pro->value)
                ->where('plan.status', 'active')
                ->where('plan.cycle', BillingCycle::Monthly->value)
                ->has('plan.limits', 4)
                ->where('plan.limits.2.feature', Feature::AssignRoles->value)
                ->where('plan.limits.2.usage', null)
                ->where('plan.limits.2.free', false)
                ->where('plan.limits.3.feature', Feature::ViewHistory->value)
                ->where('plan.limits.3.usage', 1)
                ->where('plan.limits.3.free', 6)
                ->where('plan.limits.3.pro', null)
                ->where('plan.limits.2.pro', true)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('renewal.date', '2026-10-08T10:00:00+00:00')
                    ->has('renewal.amount')
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.total', '4.99')));
    });

    it('renders a cancelled pro team on its grace period', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $team->subscription()?->forceFill(['ends_at' => now()->addDays(10)])->save();
        $team->unsetRelation('subscriptions');

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('plan.name', Plan::Pro->value)
                ->where('plan.status', 'grace')
                ->has('plan.ends_at')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('renewal', null)));
    });

    it('hides the renewal when paddle cannot be reached', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('plan.status', 'active')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('renewal', null)));
    });

    it('renders an active pro team without a cycle when the price is unknown', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $team->subscription()?->items()->update(['price_id' => 'pri_unknown']);
        $team->unsetRelation('subscriptions');

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('plan.name', Plan::Pro->value)
                ->where('plan.status', 'active')
                ->where('plan.cycle', null));
    });
});

describe('invoice', function (): void {
    it('redirects to the invoice pdf', function (): void {
        [$user, $team] = actingMember();
        $transaction = billingTransaction($team, ['paddle_id' => 'txn_abc']);

        Http::fake([
            '*/transactions/txn_abc/invoice' => Http::response(['data' => ['url' => 'https://paddle.test/invoice.pdf']]),
        ]);

        $this->get(route('billing.teams.subscriptions.invoice', [
            'team' => $team->getRouteKey(),
            'billingTransaction' => $transaction->getKey(),
        ]))->assertRedirect('https://paddle.test/invoice.pdf');
    });

    it('returns 404 when the transaction has no invoice', function (): void {
        [$user, $team] = actingMember();
        $transaction = billingTransaction($team, ['invoice_number' => null]);

        $this->get(route('billing.teams.subscriptions.invoice', [
            'team' => $team->getRouteKey(),
            'billingTransaction' => $transaction->getKey(),
        ]))->assertNotFound();
    });

    it('scopes the transaction to the route team', function (): void {
        [$user, $team] = actingMember();
        $transaction = billingTransaction(Team::factory()->create());

        $this->get(route('billing.teams.subscriptions.invoice', [
            'team' => $team->getRouteKey(),
            'billingTransaction' => $transaction->getKey(),
        ]))->assertNotFound();
    });
});

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

describe('redirectToCustomerPortal', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('billing.teams.subscriptions.redirect-to-customer-portal', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('billing.teams.subscriptions.redirect-to-customer-portal', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('creates a paddle customer and redirects to the portal', function (): void {
        [$user, $team] = actingMember();
        config()->set('cashier.api_key', 'test_key');
        Http::fake([
            '*/customers/*/portal-sessions' => Http::response(['data' => [
                'urls' => ['general' => ['overview' => 'https://paddle.test/portal']],
            ]]),
            '*/customers*' => Http::response(['data' => [[
                'email' => $user->email,
                'id' => 'ctm_test',
                'name' => $team->name,
            ]]]),
        ]);

        $this->get(route('billing.teams.subscriptions.redirect-to-customer-portal', ['team' => $team->getRouteKey()]))
            ->assertRedirect('https://paddle.test/portal');

        expect($team->fresh()?->customer?->paddle_id)->toBe('ctm_test');
    });

    it('includes the subscription on the portal session when the team is subscribed', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $team->customer()->create([
            'email' => $user->email,
            'name' => $team->name,
            'paddle_id' => 'ctm_test',
        ]);
        config()->set('cashier.api_key', 'test_key');
        Http::fake([
            '*/customers/*/portal-sessions' => Http::response(['data' => [
                'urls' => ['general' => ['overview' => 'https://paddle.test/portal']],
            ]]),
        ]);

        $this->get(route('billing.teams.subscriptions.redirect-to-customer-portal', ['team' => $team->getRouteKey()]))
            ->assertRedirect('https://paddle.test/portal');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/portal-sessions')
            && $request['subscription_ids'] === $team->subscription()?->paddle_id);
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

describe('permissions', function (): void {
    it('lets only the owner reach the billing pages', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertOk();
    });

    it('forbids an admin and a member from the owner-only billing routes', function (Role $role): void {
        [$user, $team] = actingMember($role);
        subscribeToPro($team);
        $transaction = billingTransaction($team);

        $this->get(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]))
            ->assertForbidden();

        $this->get(route('billing.teams.subscriptions.redirect-to-customer-portal', ['team' => $team->getRouteKey()]))
            ->assertForbidden();

        $this->get(route('billing.teams.subscriptions.subscribe', ['team' => $team->getRouteKey(), 'cycle' => BillingCycle::Monthly->value]))
            ->assertForbidden();

        $this->get(route('billing.teams.subscriptions.invoice', ['team' => $team->getRouteKey(), 'billingTransaction' => $transaction->getKey()]))
            ->assertForbidden();
    })->with([
        'admin' => [Role::Admin],
        'member' => [Role::Member],
    ]);

    it('points a free team member who cannot manage billing at the owner', function (Role $role): void {
        [$user, $team] = actingMember($role);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('billing/teams/subscriptions/upgrade')
                ->where('plan', Plan::Free->value)
                ->where('canManageBilling', false)
                ->where('owner.id', $team->owner?->getKey())
                ->where('owner.name', $team->owner?->name));
    })->with([
        'admin' => [Role::Admin],
        'member' => [Role::Member],
    ]);

    it('offers checkout to the owner of a free team', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('canManageBilling', true));
    });
});

describe('switch features', function (): void {
    it('lists member roles as a pro-only feature on the upgrade modal', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::AssignRoles->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('billing/teams/subscriptions/upgrade')
                ->where('feature', Feature::AssignRoles->value)
                ->where('canManageBilling', true)
                ->where('owner.id', $user->getKey())
                ->has('features', count(Feature::cases()))
                ->where('features.3.feature', Feature::AssignRoles->value)
                ->where('features.3.label', Feature::AssignRoles->getLabel())
                ->where('features.3.usage', null)
                ->where('features.3.free', false)
                ->where('features.3.pro', true));
    });

    it('keeps reporting a quota feature with its limits', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::AddAccounts->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('features.0.free', 3)
                ->where('features.0.pro', null)
                ->where('features.0.usage', null));
    });
});
