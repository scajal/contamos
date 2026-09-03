<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\UpdateAccount;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\UpdateAccount.
 */
it('throws when an account was not provided', function (): void {
    (new UpdateAccount)->using(['name' => 'Checking', 'type' => AccountType::Bank->value], []);
})->throws(\Exception::class, 'An account was not set for the update.');

it('updates the account and syncs currencies', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->bank()->for($team)->create(['name' => 'Old']);
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $updated = (new UpdateAccount)
        ->for($account)
        ->using(['name' => 'New', 'type' => AccountType::Cash->value], [$currencyId]);

    expect($updated->name)->toBe('New')
        ->and($updated->type)->toBe(AccountType::Cash)
        ->and($updated->currencies()->pluck($currency->qualifyColumn($currency->getKeyName()))->all())->toBe([$currencyId]);
});
