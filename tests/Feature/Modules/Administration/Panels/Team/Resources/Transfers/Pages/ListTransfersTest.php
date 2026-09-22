<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\ListTransfers;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer;
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

it('describes each leg by its account and amount', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $origin->activate();
    $destination->activate();
    actingOperator();
    memberOf($team);
    onTeamPanel($team);

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    Livewire::test(ListTransfers::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$transfer])
        ->assertSee($origin->name)
        ->assertSee($destination->name);
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
