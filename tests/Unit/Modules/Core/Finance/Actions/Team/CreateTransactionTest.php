<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateTransaction.
 */
it('throws when a team was not provided', function (): void {
    (new CreateTransaction)->using([
        'amount' => '10',
        'category' => Category::Food->value,
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
            'category' => Category::Food->value,
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
            'category' => Category::Salary->value,
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Income->value,
        ]);

    expect($transaction->user?->is($user))->toBeTrue();
});

it('attaches tags after creating the transaction', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $tag = Tag::factory()->active()->for($team)->create();

    $transaction = (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'category' => Category::Food->value,
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ], [$tag->getKey()]);

    expect($transaction->tags()->pluck($tag->qualifyColumn($tag->getKeyName()))->all())->toBe([$tag->getKey()]);
});

it('throws when a tag belongs to another team', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $tag = Tag::factory()->active()->for(Team::factory())->create();

    (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'category' => Category::Food->value,
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ], [$tag->getKey()]);
})->throws(\InvalidArgumentException::class);

it('records the category the type accepts', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    $transaction = (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'category' => Category::Salary->value,
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Income->value,
        ]);

    expect($transaction->category)->toBe(Category::Salary);
});

it('refuses a category that belongs to the other type', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'category' => Category::Salary->value,
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ]);
})->throws(Exception::class, 'The category salary does not belong to a movement of type expense.');

it('refuses an income or an expense without a category', function (TransactionType $type): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => $type->value,
        ]);
})->with([
    'expense' => [TransactionType::Expense],
    'income' => [TransactionType::Income],
])->throws(Exception::class);

it('refuses a category on a movement between the team accounts', function (TransactionType $type): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'category' => Category::Other->value,
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => $type->value,
        ]);
})->with([
    'opening balance' => [TransactionType::OpeningBalance],
    'transfer in' => [TransactionType::TransferIn],
    'transfer out' => [TransactionType::TransferOut],
])->throws(Exception::class);

it('records no category on a movement between the team accounts', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();

    $transaction = (new CreateTransaction)
        ->for($team)
        ->using([
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => '2024-01-01 00:00:00',
            'quote' => '1',
            'type' => TransactionType::TransferOut->value,
        ]);

    expect($transaction->category)->toBeNull();
});
