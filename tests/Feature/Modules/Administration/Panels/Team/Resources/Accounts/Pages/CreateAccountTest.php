<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\CreateAccount;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\CreateAccount.
 */
it('creates an account for the current tenant with a balance per currency', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create();

    Livewire::test(CreateAccount::class)
        ->fillForm([
            'name' => 'Caja chica',
            'type' => AccountType::Cash->value,
            'currency_ids' => [$currency->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = Account::query()->where('name', 'Caja chica')->sole();

    expect($account->team_id)->toBe($team->getKey())
        ->and($account->currencies()->pluck('currencies.id')->all())->toBe([$currency->getKey()]);
});

it('requires a name, a type and at least one currency', function (): void {
    actingOperatorOnTeamPanel();

    Livewire::test(CreateAccount::class)
        ->fillForm(['name' => null, 'type' => null, 'currency_ids' => []])
        ->call('create')
        ->assertHasFormErrors(['name', 'type', 'currency_ids']);
});
