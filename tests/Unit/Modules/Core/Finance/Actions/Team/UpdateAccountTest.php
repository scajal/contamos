<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Actions\Team\UpdateAccount;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateAccount.
 */
it('throws when an account was not provided', function (): void {
    (new UpdateAccount(new CreateTransaction))->using(['name' => 'Checking', 'type' => AccountType::Bank->value], []);
})->throws(\Exception::class, 'An account was not set for the update.');

it('updates the account and syncs currencies', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->bank()->for($team)->create(['name' => 'Old']);
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $updated = (new UpdateAccount(new CreateTransaction))
        ->for($account)
        ->using(['name' => 'New', 'type' => AccountType::Cash->value], [$currencyId]);

    expect($updated->name)->toBe('New')
        ->and($updated->type)->toBe(AccountType::Cash)
        ->and($updated->currencies()->pluck($currency->qualifyColumn($currency->getKeyName()))->all())->toBe([$currencyId]);
});

it('creates an opening balance row for a newly attached currency and bumps the pivot balance', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $account = Account::factory()->bank()->for($team)->create(['name' => 'Checking']);
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $updated = (new UpdateAccount(new CreateTransaction))
        ->for($account)
        ->by($user)
        ->using(['name' => 'Checking', 'type' => AccountType::Bank->value], [$currencyId], [
            $currencyId => ['amount' => '50.00', 'occurred_at' => '2024-01-01T00:00:00+00:00', 'quote' => '1'],
        ]);

    $transaction = $updated->transactions()->sole();

    expect($transaction->type)->toBe(TransactionType::OpeningBalance)
        ->and((float) $transaction->amount)->toBe(50.0)
        ->and($transaction->user_id)->toBe($user->getKey())
        ->and((float) $updated->currencies()->firstOrFail()->pivot->balance)->toBe(50.0);
});

it('leaves existing opening balance rows untouched', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->bank()->for($team)->create();
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $account->currencies()->attach($currencyId, ['balance' => '75']);

    (new UpdateAccount(new CreateTransaction))
        ->for($account)
        ->using(['name' => 'Checking', 'type' => AccountType::Bank->value], [$currencyId]);

    expect($account->transactions()->count())->toBe(0)
        ->and((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(75.0);
});
