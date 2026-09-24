<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\PauseRecurringRule;
use App\Modules\Core\Finance\Models\Team\RecurringRule;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\PauseRecurringRule.
 */
it('throws when a team was not provided', function (): void {
    $rule = RecurringRule::factory()->create();

    expect(fn () => (new PauseRecurringRule)->using($rule))
        ->toThrow(Exception::class, 'A recurring rule cannot be paused without a team.');

    expect($rule->refresh()->is_active)->toBeTrue();
});
