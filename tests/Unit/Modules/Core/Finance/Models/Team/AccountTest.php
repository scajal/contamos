<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Models\Team\Account.
 */
it('scopes accounts by the is_active string flag', function (): void {
    $team = Team::factory()->create();
    $active = Account::factory()->active()->for($team)->create(['name' => 'Checking']);
    $inactive = Account::factory()->inactive()->for($team)->create(['name' => 'Wallet']);

    expect(Account::query()->isActive('true')->pluck($active->getKeyName())->all())->toBe([$active->getKey()])
        ->and(Account::query()->isActive('false')->pluck($inactive->getKeyName())->all())->toBe([$inactive->getKey()]);
});

it('exposes the team and transactions of the account', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->for($team)->create();
    $currency = Currency::factory()->create();
    $transaction = Transaction::factory()->create([
        $account->getForeignKey() => $account->getKey(),
        $currency->getForeignKey() => $currency->getKey(),
        $account->team()->getForeignKeyName() => $team->getKey(),
    ]);

    expect($account->team->is($team))->toBeTrue()
        ->and($account->transactions->contains($transaction))->toBeTrue();
});
