<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\BillingTransactionResource;
use App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Pages\ViewBillingTransaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Pages\ViewBillingTransaction.
 */
it('renders the billing transaction', function (): void {
    actingOperator();
    $team = Team::factory()->create();

    $transaction = $team->billingTransactions()->create([
        'billed_at' => CarbonImmutable::now(),
        'currency' => 'USD',
        'invoice_number' => 'INV-1',
        'paddle_id' => 'txn_'.fake()->uuid(),
        'status' => 'completed',
        'tax' => '0',
        'total' => '499',
    ]);

    Livewire::test(ViewBillingTransaction::class, ['record' => $transaction->getKey()])
        ->assertOk()
        ->assertSee('INV-1');

    expect(BillingTransactionResource::getModelLabel())->toBe(trans('billing transaction'));
});
