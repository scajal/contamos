<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\ViewTeam;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\ViewTeam.
 */
it('renders the team', function (): void {
    actingOperator();
    $team = Team::factory()->create(['name' => 'Los Pinos']);

    Livewire::test(ViewTeam::class, ['record' => $team->getRouteKey()])
        ->assertOk()
        ->assertSee('Los Pinos');
});
