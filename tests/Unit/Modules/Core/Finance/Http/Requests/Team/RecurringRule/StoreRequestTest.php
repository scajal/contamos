<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\StoreRequest;

/**
 * Unit tests for \App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\StoreRequest
 * branches that HTTP validation does not reach.
 */
it('treats a new recurring rule as carrying no tags', function (): void {
    $request = StoreRequest::create('/', 'POST');
    $attachedTagIds = new ReflectionMethod(StoreRequest::class, 'attachedTagIds');

    expect($attachedTagIds->invoke($request))->toBe([]);
});
