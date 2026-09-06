<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Enums\Subscription\BillingInterval;
use App\Modules\Billing\Http\Requests\Team\Subscription\UpdateRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Objects\Toast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SubscriptionController
{
    /**
     * Display the team's subscription.
     */
    public function index(Team $team, #[CurrentUser] User $user): Response
    {
        $subscription = $this->subscriptionOf($team);

        // Paddle checkout needs a customer id up front; create one under
        // the acting member's email the first time this page is visited
        // without an active subscription.
        if (! $subscription && ! $team->customer) {
            $team->createAsCustomer(['name' => $team->name, 'email' => $user->email]);
        }

        return Inertia::render('billing/teams/subscriptions/index', [
            'checkouts' => $subscription ? null : $this->checkouts($team),
            'limits' => $this->limits($team),
            'plan' => $team->plan()->value,
            'subscription' => $subscription ? $this->subscriptionProp($subscription) : null,
            'transactions' => Inertia::defer(
                function () use ($team): LengthAwarePaginator {
                    /** @var \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Billing\Models\Transaction, \App\Modules\Core\Identity\Models\Team> $billingTransactions */
                    $billingTransactions = $team->billingTransactions();

                    return $billingTransactions
                        ->orderByDesc('billed_at')
                        ->paginate(10)
                        ->withQueryString()
                        ->through(fn (Transaction $transaction): array => [
                            'id' => $transaction->id,
                            'invoice_number' => $transaction->invoice_number,
                            'status' => $transaction->status,
                            'total' => $transaction->total(),
                            'tax' => $transaction->tax(),
                            'billed_at' => $transaction->billed_at,
                        ]);
                },
            ),
            'usage' => $this->usage($team),
        ]);
    }

    /**
     * Cancel the team's subscription at the end of the
     * current billing period.
     */
    public function destroy(Team $team): RedirectResponse
    {
        // Find the subscription.
        $subscription = $this->subscriptionOf($team);

        // Abort if the subscription is not found.
        abort_unless($subscription instanceof Subscription, HttpResponse::HTTP_NOT_FOUND);

        // Cancel the subscription.
        $subscription->cancel();

        // Show a toast message.
        Toast::make(trans('Your subscription will end at the end of the billing period'))
            ->flash();

        return back();
    }

    /**
     * Redirect to Paddle's payment method update page.
     */
    public function paymentMethod(Team $team): RedirectResponse
    {
        // Find the subscription.
        if (! ($subscription = $this->subscriptionOf($team)) instanceof Subscription) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        // Redirect to Paddle's payment method update page.
        return $subscription->redirectToUpdatePaymentMethod();
    }

    /**
     * Undo a scheduled cancelation.
     */
    public function resume(Team $team): RedirectResponse
    {
        // Find the subscription.
        if (! ($subscription = $this->subscriptionOf($team)) instanceof Subscription) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        // Undo the scheduled cancelation.
        $subscription->stopCancelation();

        // Show a toast message.
        Toast::make(trans('Subscription resumed'))
            ->flash();

        return back();
    }

    /**
     * Swap the team's subscription to a different
     * billing interval.
     */
    public function update(Team $team, UpdateRequest $request): RedirectResponse
    {
        // Find the subscription.
        if (! ($subscription = $this->subscriptionOf($team)) instanceof Subscription) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        // Swap the subscription to a different billing interval.
        $subscription->swap(BillingInterval::from($request->validated('interval'))->priceId()); // @phpstan-ignore-line argument.type

        // Show a toast message.
        Toast::make(trans('Saved'))
            ->flash();

        return back();
    }

    /**
     * Build the Free-plan checkout options for each
     * billing interval.
     *
     * @return array<string, array<array-key, mixed>>
     */
    protected function checkouts(Team $team): array
    {
        // Build the checkout options for each billing interval.
        return Collection::make(BillingInterval::cases())
            ->mapWithKeys(fn (BillingInterval $interval): array => [
                $interval->value => $team->subscribe($interval->priceId())
                    ->returnTo(route('billing.teams.subscriptions.index', ['team' => $team->getRouteKey()]).'?checkout=success')
                    ->options(),
            ])
            ->all();
    }

    /**
     * Build the plan's limits for the features
     * shown on the billing page.
     *
     * @return array<string, int|null>
     */
    protected function limits(Team $team): array
    {
        // Build the plan's limits for the features
        // shown on the billing page.
        return Collection::make(['accounts', 'members', 'transactions'])
            ->mapWithKeys(fn (string $feature): array => [$feature => $team->plan()->limit($feature)])
            ->all();
    }

    /**
     * Get the team's default billing subscription, typed as
     * our own Subscription model since Cashier's docblocks
     * still point at its own base class.
     */
    protected function subscriptionOf(Team $team): ?Subscription
    {
        /** @var \App\Modules\Billing\Models\Subscription|null $subscription */
        $subscription = $team->subscription();

        // Return the subscription.
        return $subscription;
    }

    /**
     * Build the subscription prop for the billing
     * page.
     *
     * @return array<string, mixed>
     */
    protected function subscriptionProp(Subscription $subscription): array
    {
        // Find the subscription item.
        /** @var \App\Modules\Billing\Models\Subscription\SubscriptionItem|null $item */
        $item = $subscription->items()->first();

        // Find the next payment.
        $nextPayment = $subscription->active() ? $subscription->nextPayment() : null;

        // Return the subscription prop.
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'interval' => $item ? BillingInterval::fromPriceId($item->price_id)?->value : null,
            'on_grace_period' => $subscription->onGracePeriod(),
            'ends_at' => $subscription->ends_at,
            'past_due' => $subscription->pastDue(),
            'next_payment' => $nextPayment ? [
                'amount' => $nextPayment->amount(),
                'date' => $nextPayment->date()->toDateString(),
            ] : null,
        ];
    }

    /**
     * Build the team's current usage for the features
     * shown on the billing page.
     *
     * @return array<string, int>
     */
    protected function usage(Team $team): array
    {
        // Build the team's current usage for the features
        // shown on the billing page.
        return Collection::make(['accounts', 'members', 'transactions'])
            ->mapWithKeys(fn (string $feature): array => [$feature => $team->usage($feature)])
            ->all();
    }
}
