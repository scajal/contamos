<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Tags\Pages\CreateTag;
use App\Modules\Core\Finance\Models\Team\Tag;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Tags\Pages\CreateTag.
 */
it('creates a tag for the current tenant', function (): void {
    [, $team] = actingOperatorOnTeamPanel();

    Livewire::test(CreateTag::class)
        ->fillForm(['name' => 'Comida'])
        ->call('create')
        ->assertHasNoFormErrors();

    $tag = Tag::query()->where('name', 'Comida')->sole();

    expect($tag->team_id)->toBe($team->getKey());
});
