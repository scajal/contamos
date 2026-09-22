<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\Customer;
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
 */
class WebhookController extends CashierWebhookController
{
    /**
     * Handle a Paddle webhook call.
     */
    public function handle(Request $request): Response
    {
        return $this->__invoke($request);
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
