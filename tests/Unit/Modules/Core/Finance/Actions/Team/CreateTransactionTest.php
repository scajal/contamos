<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateTransaction.
 */
it('throws when a team was not provided', function (): void {
    (new CreateTransaction)->using([
        'amount' => '10',
        'occurred_at' => '2024-01-01 00:00:00',
        'quote' => '1',
        'type' => TransactionType::Expense->value,
        'currency_id' => Currency::factory()->create()->getKey(),
        'account_id' => Account::factory()->for(Team::factory())->create()->getKey(),
    ]);
})->throws(\Exception::class, 'A transaction cannot be created without a team.');

it('creates a transaction without associating a user when none was provided', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    $transaction = (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'description' => 'Lunch',
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ]);

    expect($transaction->user_id)->toBeNull()
        ->and($transaction->decimal_places)->toBe(2);
});

it('associates the provided user with the transaction', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    $transaction = (new CreateTransaction)
        ->for($team)
        ->by($user)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Income->value,
        ]);

    expect($transaction->user?->is($user))->toBeTrue();
});
