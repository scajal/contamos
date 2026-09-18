<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Http\Requests\Team\Account\StoreRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Bind a validator on a \App\Modules\Core\Finance\Http\Requests\Team\Account\StoreRequest
 * so opening-balance resolution can be called without HTTP validation.
 *
 * @param  array<string, mixed>  $data
 */
function accountStoreRequest(array $data): StoreRequest
{
    $request = StoreRequest::create('/accounts', 'POST', $data);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setValidator(Validator::make(
        $data,
        ['occurred_at' => ['sometimes'], 'opening_balances' => ['sometimes']],
    ));

    return $request;
}

/**
 * Unit tests for \App\Modules\Core\Finance\Http\Requests\Team\Account\StoreRequest
 * branches that HTTP validation does not reach.
 */
it('returns no opening balances when the occurred at value is not a string', function (): void {
    $request = accountStoreRequest([
        'occurred_at' => 1_717_200_000,
        'opening_balances' => ['currency-id' => '100'],
    ]);

    expect($request->openingBalances())->toBe([]);
});

it('skips an opening balance whose currency does not exist', function (): void {
    $request = accountStoreRequest([
        'occurred_at' => '2024-06-01T12:00:00+00:00',
        'opening_balances' => ['missing-currency-id' => '100'],
    ]);

    expect($request->openingBalances())->toBe([]);
});

it('skips an opening balance that has no resolvable quote', function (): void {
    $currency = Currency::factory()->fiat()->create();

    $currencyId = $currency->getKey();

    if (! is_string($currencyId)) {
        throw new InvalidArgumentException('Expected a string currency key.');
    }

    $request = accountStoreRequest([
        'occurred_at' => '2024-06-01T12:00:00+00:00',
        'opening_balances' => [$currencyId => '100'],
    ]);

    expect($request->openingBalances())->toBe([]);
});
