<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;

/**
 * Feature tests for \App\Modules\Billing\Http\Controllers\PaddleWebhookController.
 */
describe('transaction.completed', function (): void {
    it('records the transaction under billing_transactions, not the Finance transactions table', function (): void {
        config()->set('cashier.webhook_secret', null);
        $team = Team::factory()->create();
        $customer = $team->customer()->create(['name' => $team->name, 'email' => 'billing@example.com', 'paddle_id' => 'cus_test123']);

        $payload = [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_test123',
                'customer_id' => $customer?->paddle_id,
                'subscription_id' => null,
                'invoice_number' => 'INV-001',
                'status' => 'completed',
                'custom_data' => null,
                'details' => ['totals' => ['total' => '999', 'tax' => '0']],
                'currency_code' => 'USD',
                'billed_at' => now()->toIso8601String(),
            ],
        ];

        $this->postJson('paddle/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('billing_transactions', [
            'paddle_id' => 'txn_test123',
            'billable_id' => $team->getKey(),
        ]);
        $this->assertDatabaseMissing((new \App\Modules\Core\Finance\Models\Team\Transaction)->getTable(), [
            'team_id' => $team->getKey(),
        ]);
    });
});

describe('subscription.created', function (): void {
    it('creates a billing subscription for the team', function (): void {
        config()->set('cashier.webhook_secret', null);
        $team = Team::factory()->create();
        $customer = $team->customer()->create(['name' => $team->name, 'email' => 'billing@example.com', 'paddle_id' => 'cus_test123']);

        $payload = [
            'event_type' => 'subscription.created',
            'data' => [
                'id' => 'sub_test123',
                'customer_id' => $customer?->paddle_id,
                'status' => 'active',
                'items' => [
                    [
                        'price' => ['id' => 'pri_month', 'product_id' => 'pro_test'],
                        'status' => 'active',
                        'quantity' => 1,
                    ],
                ],
                'custom_data' => null,
            ],
        ];

        $this->postJson('paddle/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('billing_subscriptions', [
            'paddle_id' => 'sub_test123',
            'billable_id' => $team->getKey(),
            'status' => 'active',
        ]);
    });
});
