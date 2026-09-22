<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\CreateTransaction;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Transactions\Pages\CreateTransaction.
 *
 * The page writes through \App\Modules\Core\Finance\Actions\Team\CreateTransaction,
 * so the balance the entry moves is applied with it. A plain Filament
 * create would leave the account holding a stale balance.
 */
it('records the transaction and moves the account balance', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->active()->create();
    $account->currencies()->attach($currency, ['balance' => '0']);

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'type' => TransactionType::Income->value,
            'amount' => '100',
            'quote' => '1',
            'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
            'description' => 'Sueldo',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->where('description', 'Sueldo')->sole();

    expect($transaction->team_id)->toBe($team->getKey())
        ->and((float) $account->currencies()->where('currencies.id', $currency->getKey())->sole()->pivot?->balance)->toBe(100.0);
});
