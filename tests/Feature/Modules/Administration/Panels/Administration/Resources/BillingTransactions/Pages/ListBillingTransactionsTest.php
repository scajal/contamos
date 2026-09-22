<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Pages\ListBillingTransactions;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Pages\ListBillingTransactions.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.billing-transactions.index'))
        ->assertRedirectToRoute('login');
});

it('lists the billing transactions of every team', function (): void {
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

    Livewire::test(ListBillingTransactions::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$transaction]);
});
