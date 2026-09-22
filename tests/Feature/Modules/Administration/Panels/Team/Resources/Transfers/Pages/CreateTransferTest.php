<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\CreateTransfer;
use App\Modules\Core\Finance\Models\Team\Transfer;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transfers\Pages\CreateTransfer.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\CreateTransfer,
 * which records both legs and the balances they move.
 */
it('records the transfer and moves both balances', function (): void {
    [$team, $origin, $destination, $currency] = transferSides();
    $origin->activate();
    $destination->activate();
    actingOperator();
    memberOf($team);
    onTeamPanel($team);

    Livewire::test(CreateTransfer::class)
        ->fillForm(transferAttributes($origin, $destination, $currency, $currency, ['tag_ids' => []]))
        ->call('create')
        ->assertHasNoFormErrors();

    $transfer = Transfer::query()->sole();

    expect($transfer->team_id)->toBe($team->getKey())
        ->and(balanceOf($origin, $currency))->toBe(75.0)
        ->and(balanceOf($destination, $currency))->toBe(25.0);
});
