<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\ListTransfers;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\ListTransfers.
 */
it('lists the transfers of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $transfer = Transfer::factory()->for($team)->create();

    Livewire::test(ListTransfers::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$transfer]);
});

it('hides the transfers of another team', function (): void {
    [, $team] = actingOperator();
    $mine = Transfer::factory()->for($team)->create();
    $theirs = Transfer::factory()->for(Team::factory()->create())->create();

    onTeamPanel($team);

    Livewire::test(ListTransfers::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
