<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\AccountCurrency;
use App\Modules\Core\Identity\Models\Team;

/**
 * Unit tests for \App\Modules\Core\Finance\Models\Team\AccountCurrency.
 */
it('exposes the account and currency of the pivot', function (): void {
    $team = Team::factory()->create();
    $account = Account::factory()->for($team)->create();
    $currency = Currency::factory()->create();
    $account->currencies()->attach($currency);

    $pivot = $account->currencies()->first()?->pivot;

    expect($pivot)->toBeInstanceOf(AccountCurrency::class);

    if (! $pivot instanceof AccountCurrency) {
        return;
    }

    expect($pivot->account->is($account))->toBeTrue()
        ->and($pivot->currency->is($currency))->toBeTrue();
});
