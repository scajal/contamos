<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateRecurringRule;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
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
        'category' => Category::Food->value,
        'currency_id' => $currency->getKey(),
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'type' => TransactionType::Expense,
    ]);
})->throws(Exception::class, 'A recurring rule cannot be created without a team.');

it('records the category the rule type accepts', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->create();

    $rule = (new CreateRecurringRule)->for($team)->using([
        'account_id' => $account->getKey(),
        'amount' => '25',
        'category' => Category::Salary->value,
        'currency_id' => $currency->getKey(),
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'type' => TransactionType::Income,
    ]);

    expect($rule->category)->toBe(Category::Salary);
});

it('refuses a category that belongs to the other rule type', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->create();

    (new CreateRecurringRule)->for($team)->using([
        'account_id' => $account->getKey(),
        'amount' => '25',
        'category' => Category::Salary->value,
        'currency_id' => $currency->getKey(),
        'frequency' => Frequency::Monthly,
        'interval' => 1,
        'starts_on' => '2026-01-15',
        'time_of_day' => '09:00:00',
        'timezone' => 'UTC',
        'type' => TransactionType::Expense,
    ]);
})->throws(Exception::class, 'The category salary does not belong to a movement of type expense.');

it('refuses a rule without a category', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->create();

    (new CreateRecurringRule)->for($team)->using([
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
})->throws(Exception::class, 'A movement of type expense must carry a category.');
