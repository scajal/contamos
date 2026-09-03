<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Actions\User\CreateTeam;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\User\CreateTeam.
 */
it('creates a team without attaching a user when none was provided', function (): void {
    $team = (new CreateTeam)->using(['name' => 'Acme']);

    expect($team->users)->toHaveCount(0);

    $this->assertDatabaseHas($team->getTable(), [
        $team->getKeyName() => $team->getKey(),
        'name' => 'Acme',
    ]);
});

it('attaches the team to the provided user', function (): void {
    $user = User::factory()->create();

    $team = (new CreateTeam)
        ->for($user)
        ->using(['name' => 'Acme']);

    expect($user->belongsToTeam($team))->toBeTrue();
});
