<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateAccount;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Objects\OpeningBalance;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateAccount.
 */
it('throws when a team was not provided', function (): void {
    (new CreateAccount(new CreateTransaction))->using(['name' => 'Checking', 'type' => AccountType::Bank->value], []);
})->throws(\Exception::class, 'An account cannot be created without a team.');

it('creates an account for the team and syncs currencies', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $account = (new CreateAccount(new CreateTransaction))
        ->for($team)
        ->using(['name' => 'Checking', 'type' => AccountType::Bank->value], [$currencyId]);

    expect($account->team->is($team))->toBeTrue()
        ->and($account->currencies()->pluck($currency->qualifyColumn($currency->getKeyName()))->all())->toBe([$currencyId]);
});

it('creates an opening balance row per currency and bumps the pivot balance', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $account = (new CreateAccount(new CreateTransaction))
        ->for($team)
        ->by($user)
        ->using(['name' => 'Checking', 'type' => AccountType::Bank->value], [$currencyId], [
            $currencyId => new OpeningBalance(amount: '100.00', quote: '1', occurredAt: '2024-01-01T00:00:00+00:00'),
        ]);

    $transaction = $account->transactions()->sole();

    expect($transaction->type)->toBe(TransactionType::OpeningBalance)
        ->and((float) $transaction->amount)->toBe(100.0)
        ->and($transaction->user_id)->toBe($user->getKey())
        ->and((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(100.0);
});

it('creates no opening balance rows when none are provided', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $account = (new CreateAccount(new CreateTransaction))
        ->for($team)
        ->using(['name' => 'Checking', 'type' => AccountType::Bank->value], [$currencyId]);

    expect($account->transactions()->count())->toBe(0);
});
