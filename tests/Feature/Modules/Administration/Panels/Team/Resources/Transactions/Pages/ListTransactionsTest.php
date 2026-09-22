<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\ListTransactions;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\ListTransactions.
 */
it('lists the transactions of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $transaction = Transaction::factory()->for(Account::factory()->for($team))->create();

    Livewire::test(ListTransactions::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$transaction]);
});

it('hides the transactions of another team', function (): void {
    [, $team] = actingOperator();
    $mine = Transaction::factory()->for(Account::factory()->for($team))->create();
    $theirs = Transaction::factory()->for(Account::factory()->for(Team::factory()->create()))->create();

    onTeamPanel($team);

    Livewire::test(ListTransactions::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
