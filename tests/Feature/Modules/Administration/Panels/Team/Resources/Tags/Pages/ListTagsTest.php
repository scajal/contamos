<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Tags\Pages\ListTags;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Tags\Pages\ListTags.
 */
it('lists the tags of the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create();

    Livewire::test(ListTags::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$tag]);
});

it('hides the tags of another team', function (): void {
    [, $team] = actingOperator();
    $mine = Tag::factory()->for($team)->create();
    $theirs = Tag::factory()->for(Team::factory()->create())->create();

    onTeamPanel($team);

    Livewire::test(ListTags::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
