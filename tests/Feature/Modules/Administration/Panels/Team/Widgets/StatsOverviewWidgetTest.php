<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Widgets\LatestTransactionsWidget;
use App\Modules\Administration\Panels\Team\Widgets\StatsOverviewWidget;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Livewire\Livewire;

/**
 * Feature tests for the \App\Modules\Administration\Panels\Team\Widgets
 * shown on the team panel dashboard.
 */
it('renders the team dashboard with its stats', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    Account::factory()->for($team)->count(2)->create();

    $this->get(route('filament.administration:team.pages.dashboard', ['tenant' => $team->getRouteKey()]))
        ->assertOk();

    Livewire::test(StatsOverviewWidget::class)
        ->assertOk()
        ->assertSee(trans('Accounts'));
});

it('lists the latest transactions of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $transaction = Transaction::factory()->for(Account::factory()->for($team))->create();

    Livewire::test(LatestTransactionsWidget::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$transaction]);
});
