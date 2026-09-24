<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\ResumeRecurringRule;
use App\Modules\Core\Finance\Models\Team\RecurringRule;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\ResumeRecurringRule.
 */
it('throws when a team was not provided', function (): void {
    $rule = RecurringRule::factory()->paused()->create();

    expect(fn () => (new ResumeRecurringRule)->using($rule))
        ->toThrow(Exception::class, 'A recurring rule cannot be resumed without a team.');

    expect($rule->refresh()->is_active)->toBeFalse();
});
