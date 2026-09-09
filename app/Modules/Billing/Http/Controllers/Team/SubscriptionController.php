<?php

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Enums\BillingCycle;
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
