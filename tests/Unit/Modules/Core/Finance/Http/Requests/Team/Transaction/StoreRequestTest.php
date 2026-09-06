<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Http\Requests\Team\Transaction\StoreRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Unit tests for \App\Modules\Core\Finance\Http\Requests\Team\Transaction\StoreRequest
 * branches that HTTP validation does not reach.
 */
it('returns no tag ids when the validated tag ids value is not an array', function (): void {
    $request = StoreRequest::create('/transactions', 'POST', [
        'tag_ids' => 'not-an-array',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setValidator(Validator::make(
        ['tag_ids' => 'not-an-array'],
        ['tag_ids' => ['sometimes']],
    ));

    expect($request->tagIds())->toBe([]);
});
