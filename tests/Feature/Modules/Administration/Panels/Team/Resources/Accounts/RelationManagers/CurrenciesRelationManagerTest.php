<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ViewAccount;
use App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers\CurrenciesRelationManager;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Accounts\RelationManagers\CurrenciesRelationManager.
 */
it('lists the balances the account holds', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
    $account = Account::factory()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '12.50']);

    Livewire::test(CurrenciesRelationManager::class, [
        'ownerRecord' => $account,
        'pageClass' => ViewAccount::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$currency]);
});
