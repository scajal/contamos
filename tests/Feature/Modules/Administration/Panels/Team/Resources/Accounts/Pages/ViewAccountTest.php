<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ViewAccount;
use App\Modules\Core\Finance\Models\Team\Account;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ViewAccount.
 */
it('renders the account', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->create(['name' => 'Caja chica']);

    Livewire::test(ViewAccount::class, ['record' => $account->getKey()])
        ->assertOk()
        ->assertSee('Caja chica');
});
