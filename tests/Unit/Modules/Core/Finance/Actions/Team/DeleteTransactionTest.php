<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\DeleteTransaction;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\DeleteTransaction.
 */
it('throws when a team was not provided', function (): void {
    (new DeleteTransaction)->using(Transaction::factory()->create());
})->throws(\Exception::class, 'A transaction cannot be deleted without a team.');

it('reverts the account balance when deleting an expense', function (): void {
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

    (new DeleteTransaction)
        ->for($team)
        ->using($transaction);

    expect(Transaction::query()->find($transaction->getKey()))->toBeNull()
        ->and((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(100.0);
});

it('reverts the account balance when deleting an income', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '110.50']);
    $transaction = Transaction::factory()->income()->create([
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'currency_id' => $currency->getKey(),
        'team_id' => $team->getKey(),
    ]);

    (new DeleteTransaction)
        ->for($team)
        ->using($transaction);

    expect((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(100.0);
});

it('does not delete a transaction whose account belongs to another team', function (): void {
    $team = Team::factory()->create();
    $transaction = Transaction::factory()->expense()->create();

    expect(fn () => (new DeleteTransaction)->for($team)->using($transaction))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class)
        ->and(Transaction::query()->find($transaction->getKey()))->not->toBeNull();
});
