<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\EditAccount;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\EditAccount.
 */
it('updates the account name and the currencies it holds', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $held = Currency::factory()->active()->fiat()->create();
    $added = Currency::factory()->active()->fiat()->create();
    $account = Account::factory()->for($team)->bank()->create(['name' => 'Old']);
    $account->currencies()->attach($held, ['balance' => '0']);

    Livewire::test(EditAccount::class, ['record' => $account->getKey()])
        ->fillForm([
            'name' => 'Caja chica',
            'type' => AccountType::Cash->value,
            'currency_ids' => [$held->getKey(), $added->getKey()],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($account->fresh()?->name)->toBe('Caja chica')
        ->and($account->fresh()?->type)->toBe(AccountType::Cash)
        ->and($account->currencies()->pluck('currencies.id')->sort()->values()->all())
        ->toBe(collect([$held->getKey(), $added->getKey()])->sort()->values()->all());
});
