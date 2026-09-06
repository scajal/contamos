<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Core\Identity\Models\Team;
use Carbon\Carbon;
use Laravel\Paddle\Events\TransactionCompleted;
use Laravel\Paddle\Http\Controllers\WebhookController;

class PaddleWebhookController extends WebhookController
{
    /**
     * Handle a transaction completed notification, writing through the
     * team's billing transactions instead of Cashier's default
     * `transactions()` relation, which the Finance module
     * shadows with unrelated team transactions.
     *
     * @param  array{data: array{id: string, customer_id: string, subscription_id: string|null, invoice_number: string|null, status: string, details: array{totals: array{total: string, tax: string}}, currency_code: string, billed_at: string}}  $payload
     */
    protected function handleTransactionCompleted(array $payload): void
    {
        $data = $payload['data'];

        // Check if the transaction already exists.
        if ($this->transactionExists($data['id'])) {
            return;
        }

        // Find the billable.
        /** @var \App\Modules\Core\Identity\Models\Team|null $billable */
        if (! ($billable = $this->findBillable($data['customer_id'])) instanceof Team) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Relations\HasMany<\App\Modules\Billing\Models\Transaction, \App\Modules\Core\Identity\Models\Team> $billingTransactions */
        $billingTransactions = $billable->billingTransactions();

        // Create the transaction.
        $transaction = $billingTransactions->create([
            'paddle_id' => $data['id'],
            'paddle_subscription_id' => $data['subscription_id'],
            'invoice_number' => $data['invoice_number'],
            'status' => $data['status'],
            'total' => $data['details']['totals']['total'],
            'tax' => $data['details']['totals']['tax'],
            'currency' => $data['currency_code'],
            'billed_at' => Carbon::parse($data['billed_at'], 'UTC'),
        ]);

        // Dispatch the event.
        TransactionCompleted::dispatch($billable, $transaction, $payload);
    }
}
