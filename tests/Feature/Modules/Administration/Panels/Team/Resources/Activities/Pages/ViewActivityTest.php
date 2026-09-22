<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Activities\Pages\ViewActivity;
use App\Modules\Core\Finance\Models\Team\Tag;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Activities\Pages\ViewActivity.
 */
it('renders the activity', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    Tag::factory()->for($team)->create(['name' => 'Comida']);

    $activity = activitiesOf($team)->first();

    expect($activity)->not->toBeNull();

    Livewire::test(ViewActivity::class, ['record' => $activity->getKey()])
        ->assertOk();
});
