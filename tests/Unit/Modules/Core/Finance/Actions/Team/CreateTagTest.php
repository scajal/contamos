<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\CreateTag;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateTag.
 */
it('throws when a team was not provided', function (): void {
    (new CreateTag)->using(['name' => 'Comida']);
})->throws(Exception::class, 'A tag cannot be created without a team.');

it('creates a tag for the team', function (): void {
    $team = Team::factory()->create();

    $tag = (new CreateTag)->for($team)->using(['name' => 'Comida']);

    expect($tag->team->is($team))->toBeTrue()
        ->and($tag->name)->toBe('Comida');
});
