<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateRecurringRule;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateRecurringRule.
 */
it('throws when a team was not provided', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();
    $account = Account::factory()->for($team)->create();

    (new CreateRecurringRule)->using([
        'account_id' => $account->getKey(),
        'amount' => '25',
        'currency_id' => $currency->getKey(),
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'type' => TransactionType::Expense,
    ]);
})->throws(Exception::class, 'A recurring rule cannot be created without a team.');
