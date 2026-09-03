<?php

declare(strict_types=1);

use App\Modules\Support\Objects\Toast;
use Inertia\Support\SessionKey;

/**
 * Unit tests for \App\Modules\Support\Objects\Toast.
 */
it('builds an array with a generated identifier and the given message', function (): void {
    $toast = Toast::make('Hello');

    expect($toast->toArray())
        ->toHaveKeys(['id', 'message'])
        ->and($toast->toArray()['id'])->toBeString()->not->toBeEmpty()
        ->and($toast->toArray()['message'])->toBe('Hello');
});

it('flashes the toast onto the inertia session', function (): void {
    $toast = Toast::make('Hello');

    $toast->flash();

    expect(session()->get(SessionKey::FLASH_DATA))->toBe([
        'toast' => $toast->toArray(),
    ]);
});
