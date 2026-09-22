<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Widgets\StatsOverviewWidget;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Widgets\StatsOverviewWidget.
 */
it('renders the administration dashboard with its stats', function (): void {
    actingOperator();
    Team::factory()->count(2)->create();

    $this->get(route('filament.administration.pages.dashboard'))
        ->assertOk();

    Livewire::test(StatsOverviewWidget::class)
        ->assertOk()
        ->assertSee(trans('Teams'));
});
