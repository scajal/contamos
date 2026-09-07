<?php

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
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
        // The current plan of the team.
        $plan = $team->plan();

        // Get the current subscription of the team
        // to the plan.
        /** @var \App\Modules\Billing\Models\Subscription\SubscriptionItem|null $subscription */
        $subscription = $team->subscription()?->items()->first();

        return Inertia::render('billing/teams/subscriptions/index', [
            'plan' => [
                'features' => collect($plan->features())->map(fn (Feature $feature): array => [
                    'limit' => $feature->limitOn($plan),
                    'name' => $feature->getLabel(),
                    'usage' => $feature->usage($team),
                    'value' => $feature->value,
                ]),
                'name' => $plan->getLabel(),
                'subscription' => $subscription ? ['status' => $subscription->status] : null,
                'value' => $team->plan()->value,
            ],
            'team' => $team,
            'transactions' => Inertia::defer(
                fn (): LengthAwarePaginator => $team
                    ->billingTransactions()
                    ->paginate(10)
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
        ]);
    }

    /**
     * Redirect the team to the customer portal.
     */
    public function redirectToCustomerPortal(Team $team): RedirectResponse
    {
        return $team->redirectToCustomerPortal();
    }
}
