<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\StoreRequest;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Http\Requests\Team\RecurringRule\StoreRequest
 * branches that HTTP validation does not reach.
 */
it('treats a new recurring rule as carrying no tags', function (): void {
    $team = Team::factory()->create();
    $tag = Tag::factory()->for($team)->create(['is_active' => false]);
    $request = StoreRequest::create('/', 'POST');
    $tagIsSelectable = new ReflectionMethod(StoreRequest::class, 'recurringRuleTagIsSelectable');
    $failed = false;

    // A new rule carries no tags, so an inactive one cannot be kept.
    $tagIsSelectable->invoke($request, $team, [])('tag_ids.0', $tag->getKey(), function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});
