<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\UpdateTransaction;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateTransaction.
 */
it('throws when a team was not provided', function (): void {
    (new UpdateTransaction)->using(Transaction::factory()->create(), [
        'amount' => '10',
        'occurred_at' => '2024-01-01 00:00:00',
        'quote' => '1',
        'type' => TransactionType::Expense->value,
        'currency_id' => Currency::factory()->create()->getKey(),
        'account_id' => Account::factory()->for(Team::factory())->create()->getKey(),
    ]);
})->throws(\Exception::class, 'A transaction cannot be updated without a team.');

it('reverts and reapplies the balance when the amount changes', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '89.50']);
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    $updated = (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $account->getKey(),
            'amount' => '20.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ]);

    expect($updated->getKey())->toBe($transaction->getKey())
        ->and((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(79.5);
});

it('moves the balance across accounts when the account changes', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $originAccount = Account::factory()->bank()->for($team)->create();
    $destinationAccount = Account::factory()->bank()->for($team)->create();
    $originAccount->currencies()->attach($currency, ['balance' => '89.50']);
    $destinationAccount->currencies()->attach($currency, ['balance' => '50.00']);
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $originAccount->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $destinationAccount->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ]);

    expect((float) $originAccount->currencies()->firstOrFail()->pivot->balance)->toBe(100.0)
        ->and((float) $destinationAccount->currencies()->firstOrFail()->pivot->balance)->toBe(39.5);
});

it('moves the balance across currencies when the currency changes', function (): void {
    $team = Team::factory()->create();
    $originCurrency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $destinationCurrency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($originCurrency, ['balance' => '89.50']);
    $account->currencies()->attach($destinationCurrency, ['balance' => '20.00']);
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $originCurrency->getKey(),
        'decimal_places' => 2,
        'team_id' => $team->getKey(),
    ]);

    $updated = (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $destinationCurrency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ]);

    expect((float) $account->currencies()->whereKey($originCurrency->getKey())->firstOrFail()->pivot->balance)->toBe(100.0)
        ->and((float) $account->currencies()->whereKey($destinationCurrency->getKey())->firstOrFail()->pivot->balance)->toBe(9.5)
        ->and($updated->currency_id)->toBe($destinationCurrency->getKey())
        ->and($updated->decimal_places)->toBe($destinationCurrency->decimal_places);
});

it('reverts and reapplies the balance with the inverse sign when the type flips', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '89.50']);
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Income->value,
        ]);

    expect((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(110.5);
});

it('syncs the tag set to the submitted ids', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '100.00']);
    $existingTag = Tag::factory()->active()->for($team)->create();
    $newTag = Tag::factory()->active()->for($team)->create();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);
    $transaction->attachTags([$existingTag->getKey()]);

    (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ], [$newTag->getKey()]);

    expect($transaction->tags()->pluck($newTag->qualifyColumn($newTag->getKeyName()))->all())->toBe([$newTag->getKey()]);
});

it('throws when a tag belongs to another team', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '100.00']);
    $tag = Tag::factory()->active()->for(Team::factory())->create();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    expect(fn () => (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $account->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ], [$tag->getKey()]))
        ->toThrow(\InvalidArgumentException::class);
});

it('refuses an account belonging to another team', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '100.00']);
    $otherAccount = Account::factory()->bank()->for(Team::factory())->create();
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    expect(fn () => (new UpdateTransaction)
        ->for($team)
        ->using($transaction, [
            'account_id' => $otherAccount->getKey(),
            'amount' => '10.50',
            'currency_id' => $currency->getKey(),
            'occurred_at' => $transaction->occurred_at,
            'quote' => '1',
            'type' => TransactionType::Expense->value,
        ]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
