<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Users\Pages\ViewUser;
use App\Modules\Administration\Panels\Administration\Resources\Users\RelationManagers\TeamsRelationManager;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Users\RelationManagers\TeamsRelationManager.
 */
it('lists the teams the user belongs to', function (): void {
    [$operator, $team] = actingOperator();

    Livewire::test(TeamsRelationManager::class, [
        'ownerRecord' => $operator,
        'pageClass' => ViewUser::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCountTableRecords(1)
        ->assertSee($team->slug);
});
