<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\UpdateRecurringRule;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateRecurringRule.
 */
it('throws when a team was not provided', function (): void {
    $rule = RecurringRule::factory()->create();

    (new UpdateRecurringRule)->using($rule, [
        'account_id' => $rule->account_id,
        'amount' => '25',
        'currency_id' => $rule->currency_id,
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'type' => TransactionType::Expense,
    ]);
})->throws(Exception::class, 'A recurring rule cannot be updated without a team.');

it('updates the rule amount', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->create();
    $rule = RecurringRule::factory()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    $updated = (new UpdateRecurringRule)->for($team)->using($rule, [
        'account_id' => $account->getKey(),
        'amount' => '40',
        'currency_id' => $currency->getKey(),
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'type' => TransactionType::Expense,
    ]);

    expect((float) $updated->amount)->toBe(40.0);
});
