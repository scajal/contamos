<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ViewAccount;
use App\Modules\Core\Finance\Models\Team\Account;
use Filament\Actions\Testing\TestAction;
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

it('activates the account', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->inactive()->create();

    Livewire::test(ViewAccount::class, ['record' => $account->getKey()])
        ->callAction(TestAction::make('activate'))
        ->assertNotified(trans('Saved'));

    expect($account->fresh()?->is_active)->toBeTrue();
});

it('deactivates the account', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->active()->create();

    Livewire::test(ViewAccount::class, ['record' => $account->getKey()])
        ->callAction(TestAction::make('deactivate'))
        ->assertNotified(trans('Saved'));

    expect($account->fresh()?->is_active)->toBeFalse();
});
