<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Activities\Pages\ListActivities;
use App\Modules\Core\Finance\Models\Team\Tag;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Activities\Pages\ListActivities.
 */
it('lists the activity recorded for the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    Tag::factory()->for($team)->create();

    $activities = activitiesOf($team);

    expect($activities)->not->toBeEmpty();

    Livewire::test(ListActivities::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords($activities);
});
