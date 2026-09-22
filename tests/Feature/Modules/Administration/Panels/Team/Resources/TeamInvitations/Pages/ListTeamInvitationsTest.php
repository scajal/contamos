<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\TeamInvitations\Pages\ListTeamInvitations;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\TeamInvitations\Pages\ListTeamInvitations.
 */
it('lists the invitations of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $invitation = TeamInvitation::factory()->for($team)->create();

    Livewire::test(ListTeamInvitations::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$invitation]);
});

it('hides the invitations of another team', function (): void {
    [, $team] = actingOperator();
    $mine = TeamInvitation::factory()->for($team)->create();
    $theirs = TeamInvitation::factory()->for(Team::factory()->create())->create();

    onTeamPanel($team);

    Livewire::test(ListTeamInvitations::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
