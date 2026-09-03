<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Models\Team\Transaction.
 */
it('fills the team from the account when the team is missing', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->for($team)->create();
    $currency = Currency::factory()->create();

    $transaction = Transaction::query()->create([
        'account_id' => $account->getKey(),
        'amount' => '10',
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'occurred_at' => '2024-01-01 00:00:00',
        'quote' => '1',
        'type' => TransactionType::Expense,
    ]);

    expect($transaction->{$transaction->team()->getForeignKeyName()})->toBe($team->getKey())
        ->and($transaction->account->is($account))->toBeTrue()
        ->and($transaction->currency->is($currency))->toBeTrue()
        ->and($transaction->team->is($team))->toBeTrue();
});

it('scopes transactions that occurred from a date', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->for($team)->create();
    $currency = Currency::factory()->create();

    $included = Transaction::factory()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
        'occurred_at' => '2024-06-01 12:00:00',
    ]);
    Transaction::factory()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
        'occurred_at' => '2024-05-01 12:00:00',
    ]);

    expect(Transaction::query()->occurredFrom('2024-06-01')->pluck($included->getKeyName())->all())
        ->toBe([$included->getKey()]);
});

it('scopes transactions that occurred until a date', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->for($team)->create();
    $currency = Currency::factory()->create();

    $included = Transaction::factory()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
        'occurred_at' => '2024-05-01 12:00:00',
    ]);
    Transaction::factory()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
        'occurred_at' => '2024-06-15 12:00:00',
    ]);

    expect(Transaction::query()->occurredUntil('2024-06-01')->pluck($included->getKeyName())->all())
        ->toBe([$included->getKey()]);
});
