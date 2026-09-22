<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\ViewTransaction;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\ViewTransaction.
 */
it('renders the transaction', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $transaction = Transaction::factory()
        ->for(Account::factory()->for($team))
        ->create(['description' => 'Sueldo']);

    Livewire::test(ViewTransaction::class, ['record' => $transaction->getKey()])
        ->assertOk()
        ->assertSee('Sueldo');
});
