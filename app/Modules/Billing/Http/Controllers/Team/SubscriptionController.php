<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Enums\BillingCycle;
use App\Modules\Billing\Enums\Feature;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Http\Requests\Team\Subscription\UpgradeRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Laravel\Paddle\Payment;
use Laravel\Pennant\Feature as Pennant;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SubscriptionController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team, Request $request): Response
    {
        return Inertia::render('billing/teams/subscriptions/index', [
            // Whether the team just came back from checkout. The page
            // polls the plan until the webhook flips it to Pro.
            'activating' => $request->boolean('activating'),
            'plan' => $this->plan($team),
            'renewal' => Inertia::defer(fn (): ?array => $this->renewal($team)),
            'team' => $team,
            'transactions' => Inertia::defer(function () use ($team): LengthAwarePaginator {
                /** @var \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Modules\Billing\Models\Transaction, \App\Modules\Core\Identity\Models\Team> $transactions */
                $transactions = $team->billingTransactions();

                return $transactions
                    ->latest('billed_at')
                    ->paginate(10);
            }),
        ]);
    }

    /**
     * Summarise the team's plan for the page: which plan, where the
     * subscription is in its lifecycle, and how much of each limit
     * the team is using. A free team can sit above the free limits
     * after an earlier Pro period; nothing is removed, it just
     * cannot add more.
     *
     * @return array{name: string, status: string, cycle: string|null, ends_at: string|null, trial_ends_at: string|null, limits: array<int, array{feature: string, label: string, usage: int|null, free: int|bool|null, pro: int|bool|null}>}
     */
    protected function plan(Team $team): array
    {
        $plan = $team->plan();
        $subscription = $this->subscription($team);

        return [
            'name' => $plan->value,
            'status' => $this->status($plan, $subscription),
            'cycle' => $this->cycle($subscription)?->value,
            'ends_at' => $subscription?->ends_at?->toIso8601String(),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'limits' => $this->limits($team),
        ];
    }

    /**
     * The team's default subscription, typed to the module's model.
     */
    protected function subscription(Team $team): ?Subscription
    {
        $subscription = $team->subscription();

        return $subscription instanceof Subscription ? $subscription : null;
    }

    /**
     * Where the subscription is in its lifecycle. A cancelled
     * subscription stays Pro until the paid period ends, which
     * is the grace period.
     */
    protected function status(Plan $plan, ?Subscription $subscription): string
    {
        if ($plan === Plan::Free || ! $subscription instanceof Subscription) {
            return 'free';
        }

        return match (true) {
            $subscription->onGracePeriod() => 'grace',
            $subscription->pastDue() => 'past_due',
            $subscription->paused() => 'paused',
            $subscription->onTrial() => 'trialing',
            default => 'active',
        };
    }

    /**
     * The billing cycle of the subscription, worked out from
     * which Pro price it carries.
     */
    protected function cycle(?Subscription $subscription): ?BillingCycle
    {
        if (! $subscription instanceof Subscription) {
            return null;
        }

        foreach (BillingCycle::cases() as $cycle) {
            if ($subscription->hasPrice(Plan::Pro->priceId($cycle))) {
                return $cycle;
            }
        }

        return null;
    }

    /**
     * The team-scoped features with the team's usage and what each
     * plan gives, in the order the upgrade modal lists them. A quota
     * feature reports its limit and usage; a switch feature reports
     * whether the plan includes it and has no usage.
     *
     * @return array<int, array{feature: string, label: string, usage: int|null, free: int|bool|null, pro: int|bool|null}>
     */
    protected function limits(Team $team): array
    {
        $limits = [];

        foreach ([Feature::AddAccounts, Feature::AddMembers, Feature::AssignRoles, Feature::ManageBudgets, Feature::ViewHistory] as $feature) {
            $limits[] = $this->row($feature, $team);
        }

        return $limits;
    }

    /**
     * A feature row for the plan comparison, with the team's usage
     * when the team is given and the feature is a quota.
     *
     * @return array{feature: string, label: string, usage: int|null, free: int|bool|null, pro: int|bool|null}
     */
    protected function row(Feature $feature, ?Team $team = null): array
    {
        return [
            'feature' => $feature->value,
            'label' => $feature->getLabel(),
            'usage' => $this->usage($feature, $team),
            'free' => $feature->valueOn(Plan::Free),
            'pro' => $feature->valueOn(Plan::Pro),
        ];
    }

    /**
     * How much of a quota feature the team is using. A switch
     * feature has no usage, and neither has a row asked for
     * without a team.
     */
    protected function usage(Feature $feature, ?Team $team): ?int
    {
        if ($feature->isSwitch() || ! $team instanceof Team) {
            return null;
        }

        /** @var \App\Modules\Billing\Features\AddAccounts|\App\Modules\Billing\Features\AddMembers|\App\Modules\Billing\Features\AddTeams|\App\Modules\Billing\Features\ViewHistory $instance */
        $instance = Pennant::instance($feature);

        return $instance->usage($team);
    }

    /**
     * The next payment Paddle has scheduled, if any. This is a
     * Paddle round trip, so it is deferred and a failure just
     * hides the line.
     *
     * @return array{amount: string, date: string}|null
     */
    protected function renewal(Team $team): ?array
    {
        // The subscription is nullable because the team may not
        // have a subscription yet, in which case the renewal is
        // not applicable.
        if (! ($subscription = $this->subscription($team)) instanceof Subscription || ! $subscription->recurring()) {
            return null;
        }

        /** @var \Laravel\Paddle\Payment|null $payment */
        $payment = rescue(fn (): ?Payment => $subscription->nextPayment(), null, false);

        if (! $payment instanceof Payment) {
            return null;
        }

        return [
            'amount' => $payment->amount(),
            'date' => $payment->date()->toIso8601String(),
        ];
    }

    /**
     * Subscribe the team to the pro plan with the
     * given cycle.
     */
    public function subscribe(Team $team, BillingCycle $cycle): Modal
    {
        return Inertia::modal('billing/teams/subscriptions/subscribe', [
            // After paying, Paddle sends the browser back to the billing
            // page, which polls until the webhook activates the plan.
            'checkout' => $team->checkout(Plan::Pro->priceId($cycle))
                ->returnTo(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey(), 'activating' => 1])),
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
    public function upgrade(Team $team, UpgradeRequest $request, #[CurrentUser] User $user): Modal
    {
        return Inertia::modal('billing/teams/subscriptions/upgrade', [
            // Only the owner pays, so everybody else is told to ask
            // them instead of being offered checkout.
            'canManageBilling' => $user->hasTeamPermission($team, Permission::ManageBilling),
            'feature' => $request->feature(),
            'features' => collect(Feature::cases())
                ->map(fn (Feature $feature): array => $this->row($feature))
                ->values(),
            'owner' => $team->owner,
            'plan' => $team->plan(),
            'prices' => [
                BillingCycle::Monthly->value => Plan::Pro->price(BillingCycle::Monthly),
                BillingCycle::Yearly->value => Plan::Pro->price(BillingCycle::Yearly),
            ],
        ])->baseRoute('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]);
    }

    /**
     * Redirect to the PDF invoice Paddle issued for the transaction.
     */
    public function invoice(Team $team, Transaction $billingTransaction): RedirectResponse
    {
        $url = $billingTransaction->invoicePdf();

        // Paddle only issues invoices for completed transactions.
        abort_if(! is_string($url), HttpResponse::HTTP_NOT_FOUND);

        return redirect()->away($url);
    }

    /**
     * Redirect the team to the customer portal.
     */
    public function redirectToCustomerPortal(Team $team): RedirectResponse
    {
        return $team->redirectToCustomerPortal();
    }
}
