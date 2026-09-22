<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ListAccounts;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Accounts\Pages\ListAccounts.
 */
it('lists the accounts of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->create();

    Livewire::test(ListAccounts::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$account]);
});

it('hides the accounts of another team', function (): void {
    [, $team] = actingOperator();
    $mine = Account::factory()->for($team)->create();
    $theirs = Account::factory()->for(Team::factory()->create())->create();

    onTeamPanel($team);

    Livewire::test(ListAccounts::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('activates the account', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->inactive()->create();

    Livewire::test(ListAccounts::class)
        ->loadTable()
        ->callAction(TestAction::make('activate')->table($account))
        ->assertNotified(trans('Saved'));

    expect($account->fresh()?->is_active)->toBeTrue();
});

it('deactivates the account', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $account = Account::factory()->for($team)->active()->create();

    Livewire::test(ListAccounts::class)
        ->loadTable()
        ->callAction(TestAction::make('deactivate')->table($account))
        ->assertNotified(trans('Saved'));

    expect($account->fresh()?->is_active)->toBeFalse();
});
