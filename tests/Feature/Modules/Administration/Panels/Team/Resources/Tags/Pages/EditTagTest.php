<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Tags\Pages\EditTag;
use App\Modules\Core\Finance\Models\Team\Tag;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Tags\Pages\EditTag.
 */
it('renames the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create(['name' => 'Old']);

    Livewire::test(EditTag::class, ['record' => $tag->getKey()])
        ->fillForm(['name' => 'Comida'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($tag->fresh()?->name)->toBe('Comida');
});
