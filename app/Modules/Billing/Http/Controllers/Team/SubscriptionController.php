<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Http\Requests\Team\Subscription\UpgradeRequest;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;

class SubscriptionController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team): Response
    {
        return Inertia::render('billing/teams/subscriptions/index', [
            'team' => $team,
            'transactions' => Inertia::defer(
                fn (): LengthAwarePaginator => $team // @phpstan-ignore-line return.type False positive.
                    ->billingTransactions()
                    ->paginate(10) // @phpstan-ignore-line method.nonObject False positive.
            ),
        ]);
    }

    /**
     * Subscribe the team to the pro plan with the
     * given cycle.
     */
    public function subscribe(Team $team, BillingCycle $cycle): Modal
    {
        return Inertia::modal('billing/teams/subscriptions/subscribe', [
            'checkout' => $team->checkout(Plan::Pro->priceId($cycle)),
            // Paddle.js is loaded by the page itself, so it needs
            // the client-side settings the Blade directive used to
            // inline.
            'paddle' => [
                'token' => config()->string('cashier.client_side_token', ''),
                'sandbox' => config()->boolean('cashier.sandbox', false),
            ],
        ])->baseRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Show the upgrade modal, listing what the pro plan
     * unlocks. Shown when the team hits a free plan limit.
     */
    public function upgrade(Team $team, UpgradeRequest $request): Modal
    {
        return Inertia::modal('billing/teams/subscriptions/upgrade', [
            'feature' => $request->feature(),
            'features' => collect(Feature::cases())->map(fn (Feature $feature): array => [
                'feature' => $feature,
                'label' => $feature->getLabel(),
                'free' => $feature->limitOn(Plan::Free),
                'pro' => $feature->limitOn(Plan::Pro),
            ])->values(),
            'plan' => $team->plan(),
            'prices' => [
                BillingCycle::Monthly->value => Plan::Pro->price(BillingCycle::Monthly),
                BillingCycle::Yearly->value => Plan::Pro->price(BillingCycle::Yearly),
            ],
        ])->baseRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Redirect the team to the customer portal.
     */
    public function redirectToCustomerPortal(Team $team): RedirectResponse
    {
        return $team->redirectToCustomerPortal();
    }
}
