<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\ViewTeam;
use App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers\InvitationsRelationManager;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers\InvitationsRelationManager.
 */
it('lists pending, accepted and expired invitations', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $pending = TeamInvitation::factory()->for($team)->create();
    $accepted = TeamInvitation::factory()->for($team)->accepted()->create();
    $expired = TeamInvitation::factory()->for($team)->expired()->create();

    Livewire::test(InvitationsRelationManager::class, [
        'ownerRecord' => $team,
        'pageClass' => ViewTeam::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$pending, $accepted, $expired]);
});

it('renews a pending invitation', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $invitation = TeamInvitation::factory()->for($team)->create([
        'expires_at' => CarbonImmutable::now()->addDay(),
    ]);
    $code = $invitation->code;

    Livewire::test(InvitationsRelationManager::class, [
        'ownerRecord' => $team,
        'pageClass' => ViewTeam::class,
    ])
        ->callAction(TestAction::make('renew')->table($invitation))
        ->assertNotified(trans('Saved'));

    expect($invitation->fresh()?->code)->not->toBe($code);
});
