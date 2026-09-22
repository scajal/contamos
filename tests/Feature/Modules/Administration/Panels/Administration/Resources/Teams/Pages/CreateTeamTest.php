<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\CreateTeam;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\CreateTeam.
 */
it('creates a team and derives its slug from the name', function (): void {
    actingOperator();

    Livewire::test(CreateTeam::class)
        ->fillForm(['name' => 'Los Pinos', 'slug' => null])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Team::query()->where('name', 'Los Pinos')->sole()->slug)->toBe('los-pinos');
});

it('requires a name', function (): void {
    actingOperator();

    Livewire::test(CreateTeam::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name']);
});
