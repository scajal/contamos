<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ViewAccount;
use App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers\TransactionsRelationManager;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers\TransactionsRelationManager.
 */
it('lists the transactions of the account', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->create();
    $transaction = Transaction::factory()->for($account)->create(['description' => 'Sueldo']);

    Livewire::test(TransactionsRelationManager::class, [
        'ownerRecord' => $account,
        'pageClass' => ViewAccount::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$transaction]);
});
