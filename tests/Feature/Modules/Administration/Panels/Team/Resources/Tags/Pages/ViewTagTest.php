<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Tags\Pages\ViewTag;
use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Tags\Pages\ViewTag.
 */
it('renders the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->create(['name' => 'Comida']);

    Livewire::test(ViewTag::class, ['record' => $tag->getKey()])
        ->assertOk()
        ->assertSee('Comida');
});

it('activates the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->inactive()->create();

    Livewire::test(ViewTag::class, ['record' => $tag->getKey()])
        ->callAction(TestAction::make('activate'))
        ->assertNotified(trans('Saved'));

    expect($tag->fresh()?->is_active)->toBeTrue();
});

it('deactivates the tag', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $tag = Tag::factory()->for($team)->active()->create();

    Livewire::test(ViewTag::class, ['record' => $tag->getKey()])
        ->callAction(TestAction::make('deactivate'))
        ->assertNotified(trans('Saved'));

    expect($tag->fresh()?->is_active)->toBeFalse();
});
