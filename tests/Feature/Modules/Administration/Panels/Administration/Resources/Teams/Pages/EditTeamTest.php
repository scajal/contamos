<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\EditTeam;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\EditTeam.
 */
it('updates the team name and slug', function (): void {
    actingOperator();
    $team = Team::factory()->create(['name' => 'Old', 'slug' => 'old']);

    Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
        ->fillForm([
            'name' => 'Los Pinos',
            'slug' => 'los-pinos',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($team->fresh()?->name)->toBe('Los Pinos')
        ->and($team->fresh()?->slug)->toBe('los-pinos');
});
