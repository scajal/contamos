<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateAccount;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateAccount.
 */
it('throws when a team was not provided', function (): void {
    (new CreateAccount)->using(['name' => 'Checking', 'type' => AccountType::Bank->value], []);
})->throws(\Exception::class, 'An account cannot be created without a team.');

it('creates an account for the team and syncs currencies', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $account = (new CreateAccount)
        ->for($team)
        ->using(['name' => 'Checking', 'type' => AccountType::Bank->value], [$currencyId]);

    expect($account->team->is($team))->toBeTrue()
        ->and($account->currencies()->pluck($currency->qualifyColumn($currency->getKeyName()))->all())->toBe([$currencyId]);
});
