<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\ListTeams;
use App\Modules\Core\Identity\Models\Team;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\ListTeams.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.teams.index'))
        ->assertRedirectToRoute('login');
});

it('renders the teams index for an operator', function (): void {
    actingOperator();

    $this->get(route('filament.administration.resources.teams.index'))
        ->assertOk();
});

it('lists every team', function (): void {
    [, $team] = actingOperator();
    $other = Team::factory()->create();

    Livewire::test(ListTeams::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$team, $other]);
});

it('searches teams by name', function (): void {
    actingOperator();
    $matching = Team::factory()->create(['name' => 'Los Pinos']);
    $other = Team::factory()->create(['name' => 'Las Rosas']);

    Livewire::test(ListTeams::class)
        ->loadTable()
        ->searchTable('Pinos')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('links every team to its own panel', function (): void {
    actingOperator();
    $team = Team::factory()->create();

    expect(Filament::getPanel('team')->getUrl($team))
        ->toContain('/administration/team/'.$team->getRouteKey());
});
