<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Transaction as BillingTransaction;
use App\Modules\Core\Finance\Models\Team\Transaction as FinanceTransaction;

use function Pest\Laravel\postJson;

/**
 * Feature tests for \App\Modules\Billing\Http\Controllers\WebhookController.
 */
describe('transaction.completed', function (): void {
    it('records the paddle transaction against the team billing history', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $team->customer()->create([
            'email' => $user->email,
            'name' => $team->name,
            'paddle_id' => 'ctm_test',
        ]);

        postJson(route('billing.webhook'), [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_test',
                'customer_id' => 'ctm_test',
                'subscription_id' => $team->subscription()?->paddle_id,
                'invoice_number' => '130674-10001',
                'status' => 'completed',
                'currency_code' => 'USD',
                'billed_at' => '2026-09-22T18:39:10Z',
                'details' => ['totals' => ['total' => '499', 'tax' => '90']],
            ],
        ])->assertOk();

        expect(BillingTransaction::query()->where('paddle_id', 'txn_test')->first())
            ->not->toBeNull()
            ->billable_id->toBe($team->getKey())
            ->invoice_number->toBe('130674-10001')
            ->total->toBe('499')
            ->and(FinanceTransaction::query()->count())->toBe(0);
    });

    it('ignores a transaction it already recorded', function (): void {
        [$user, $team] = actingMember();
        $team->customer()->create([
            'email' => $user->email,
            'name' => $team->name,
            'paddle_id' => 'ctm_test',
        ]);

        $payload = [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_test',
                'customer_id' => 'ctm_test',
                'subscription_id' => null,
                'invoice_number' => null,
                'status' => 'completed',
                'currency_code' => 'USD',
                'billed_at' => '2026-09-22T18:39:10Z',
                'details' => ['totals' => ['total' => '499', 'tax' => '90']],
            ],
        ];

        postJson(route('billing.webhook'), $payload)->assertOk();
        postJson(route('billing.webhook'), $payload)->assertOk();

        expect(BillingTransaction::query()->count())->toBe(1);
    });
});
