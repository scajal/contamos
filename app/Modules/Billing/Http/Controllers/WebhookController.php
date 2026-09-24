<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\Team\NotifyProEnded;
use App\Modules\Billing\Models\Customer;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Laravel\Paddle\Events\TransactionCompleted;
use Laravel\Paddle\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cashier's webhook controller, with the transaction handler
 * pointed at the team's billing transactions. The team's own
 * transactions() relation is its finance ledger, so Cashier's
 * default handler would write Paddle transactions into it.
 * The end of a subscription also tells the Owner their team
 * dropped to Free.
 */
class WebhookController extends CashierWebhookController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected NotifyProEnded $notifyProEnded,
    ) {
        parent::__construct();
    }

    /**
     * Handle a Paddle webhook call.
     */
    public function handle(Request $request): Response
    {
        return $this->__invoke($request);
    }

    /**
     * Handle subscription canceled. Paddle sends it when the
     * subscription actually ends, not when a cancellation is
     * scheduled, so this is where a team drops from Pro.
     *
     * @param  array{data: array{id: string, status: string, canceled_at: string}}  $payload
     */
    protected function handleSubscriptionCanceled(array $payload): void
    {
        parent::handleSubscriptionCanceled($payload);

        // Claim the end of the subscription in one conditional
        // write, so a retried or concurrent webhook finds it
        // handled and the Owner hears about the drop once.
        $claimed = Subscription::query()
            ->where('paddle_id', $payload['data']['id'])
            ->whereNull('end_handled_at')
            ->update(['end_handled_at' => CarbonImmutable::now()]);

        if ($claimed === 0) {
            return;
        }

        // Only teams are billed, so a subscription of anything
        // else has no Owner to tell.
        $subscription = Subscription::query()->where('paddle_id', $payload['data']['id'])->first();

        if (! ($billable = $subscription?->billable) instanceof Team) {
            return;
        }

        $this->notifyProEnded->using($billable);
    }

    /**
     * Handle transaction completed.
     *
     * @param  array{data: array{id: string, customer_id: string, subscription_id: string|null, invoice_number: string|null, status: string, currency_code: string, billed_at: string, details: array{totals: array{total: string, tax: string}}}}  $payload
     */
    protected function handleTransactionCompleted(array $payload): void
    {
        $data = $payload['data'];

        if ($this->transactionExists($data['id'])) {
            return;
        }

        // Only teams are billed, so a customer that belongs to
        // anything else has nowhere to record the transaction.
        $customer = Customer::query()->where('paddle_id', $data['customer_id'])->first();

        if (! ($billable = $customer?->billable) instanceof Team) {
            return;
        }

        $transaction = $billable->billingTransactions()->create([
            'paddle_id' => $data['id'],
            'paddle_subscription_id' => $data['subscription_id'],
            'invoice_number' => $data['invoice_number'],
            'status' => $data['status'],
            'total' => $data['details']['totals']['total'],
            'tax' => $data['details']['totals']['tax'],
            'currency' => $data['currency_code'],
            'billed_at' => CarbonImmutable::parse($data['billed_at'], 'UTC'),
        ]);

        TransactionCompleted::dispatch($billable, $transaction, $payload);
    }
}
