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

it('filters transactions by the dates they occurred', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $inside = Transaction::factory()->for(Account::factory()->for($team))->create([
        'occurred_at' => '2024-06-15 12:00:00',
    ]);
    $outside = Transaction::factory()->for(Account::factory()->for($team))->create([
        'occurred_at' => '2023-01-01 12:00:00',
    ]);

    Livewire::test(ListTransactions::class)
        ->loadTable()
        ->filterTable('occurred_at', [
            'from' => '2024-01-01',
            'until' => '2024-12-31',
        ])
        ->assertCanSeeTableRecords([$inside])
        ->assertCanNotSeeTableRecords([$outside]);
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
