<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\ViewTransfer;
use App\Modules\Core\Finance\Actions\Team\CreateTransfer;
use App\Modules\Core\Finance\Models\Team\Transfer;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\ViewTransfer.
 *
 * The page takes the transfer back through
 * \App\Modules\Core\Finance\Actions\Team\DeleteTransfer, so both
 * legs and the balances they moved are reverted.
 */
it('renders the transfer', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $origin->activate();
    $destination->activate();
    actingOperator();
    memberOf($team);
    onTeamPanel($team);

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    Livewire::test(ViewTransfer::class, ['record' => $transfer->getKey()])
        ->assertOk();
});

it('renders a dash when a leg of the transfer is missing', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $transfer = Transfer::factory()->create([
        'description' => 'Orphaned',
        'team_id' => $team->getKey(),
    ]);

    Livewire::test(ViewTransfer::class, ['record' => $transfer->getKey()])
        ->assertOk()
        ->assertSee('-');
});

it('deletes the transfer and reverts both balances', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $origin->activate();
    $destination->activate();
    actingOperator();
    memberOf($team);
    onTeamPanel($team);

    $transfer = (new CreateTransfer)
        ->for($team)
        ->using(transferAttributes($origin, $destination, $currency, $currency));

    Livewire::test(ViewTransfer::class, ['record' => $transfer->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    expect(Transfer::query()->find($transfer->getKey()))->toBeNull()
        ->and(balanceOf($origin, $currency))->toBe(100.0)
        ->and(balanceOf($destination, $currency))->toBe(0.0);
});
