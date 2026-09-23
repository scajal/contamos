<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Widgets\StatsOverviewWidget;
use App\Modules\Core\Finance\Models\Team\Account;
use Livewire\Livewire;

/**
 * Feature tests for the \App\Modules\Administration\Panels\Team\Widgets
 * shown on the team panel dashboard.
 */
it('renders the team dashboard with its stats', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    Account::factory()->for($team)->count(2)->create();

    $this->get(route('filament.team.pages.dashboard', ['tenant' => $team->getRouteKey()]))
        ->assertOk();

    Livewire::test(StatsOverviewWidget::class)
        ->assertOk()
        ->assertSee(trans('Accounts'));
});
