<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Http\Requests\Team\Budget\StoreRequest;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Http\Requests\Team\Budget\StoreRequest
 * branches that HTTP validation does not reach.
 */
it('does not compare budget months when the start month is missing', function (): void {
    $team = Team::factory()->create();
    $request = StoreRequest::create('/', 'POST', ['from_month' => null]);
    $freeMonths = new ReflectionMethod(StoreRequest::class, 'freeMonths');
    $failed = false;

    $freeMonths->invoke($request, $team)('from_month', null, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
