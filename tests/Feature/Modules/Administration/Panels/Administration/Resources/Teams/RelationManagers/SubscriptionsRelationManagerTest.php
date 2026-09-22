<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\ViewTeam;
use App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers\SubscriptionsRelationManager;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers\SubscriptionsRelationManager.
 */
it('lists the subscriptions of the team', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    subscribeToPro($team);

    Livewire::test(SubscriptionsRelationManager::class, [
        'ownerRecord' => $team,
        'pageClass' => ViewTeam::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords(Subscription::query()->whereMorphedTo('billable', $team)->get());
});
