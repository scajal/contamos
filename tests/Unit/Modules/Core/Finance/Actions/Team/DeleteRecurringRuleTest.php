<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Actions\Team\DeleteRecurringRule;
use App\Modules\Core\Finance\Actions\Team\DeleteTransaction;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\DeleteRecurringRule.
 */
it('throws when a team was not provided', function (): void {
    (new DeleteRecurringRule(new DeleteTransaction))->using(RecurringRule::factory()->create());
})->throws(Exception::class, 'A recurring rule cannot be deleted without a team.');

it('deletes the rule', function (): void {
    $team = Team::factory()->create();
    $rule = RecurringRule::factory()->for(Account::factory()->for($team))->create();

    (new DeleteRecurringRule(new DeleteTransaction))->for($team)->using($rule);

    expect(RecurringRule::query()->find($rule->getKey()))->toBeNull();
});
