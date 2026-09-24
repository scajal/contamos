<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateRecurringRule;
use App\Modules\Core\Finance\Actions\Team\CreateTransaction;
use App\Modules\Core\Finance\Actions\Team\CreateTransactionEntry;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Unit tests for \App\Modules\Core\Finance\Actions\Team\CreateTransactionEntry.
 */

/**
 * The validated attributes of an entry recorded against the
 * given account, repeating monthly when asked to.
 *
 * @return array<string, mixed>
 */
function transactionEntryAttributes(Account $account, Currency $currency, bool $repeat): array
{
    return [
        'account_id' => $account->getKey(),
        'amount' => '10.50',
        'category' => Category::Food->value,
        'currency_id' => $currency->getKey(),
        'description' => 'Lunch',
        'occurred_at' => '2026-01-15 12:00:00',
        'quote' => '1',
        'type' => TransactionType::Expense->value,
        'repeat' => $repeat,
        'recurring_frequency' => 'monthly',
        'recurring_interval' => 1,
        'recurring_starts_on' => '2026-01-15',
        'recurring_time_of_day' => '12:00:00',
        'timezone' => 'UTC',
    ];
}

it('throws when a team was not provided', function (): void {
    $currency = Currency::factory()->fiat()->create();
    $account = Account::factory()->for(Team::factory())->create();

    (new CreateTransactionEntry(new CreateTransaction, new CreateRecurringRule))
        ->using(transactionEntryAttributes($account, $currency, repeat: false));
})->throws(Exception::class, 'A transaction entry cannot be recorded without a team.');

it('refuses a repeating entry on a plan without recurring transactions', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->for($team)->create();
    $account->currencies()->attach($currency);

    expect(fn () => (new CreateTransactionEntry(new CreateTransaction, new CreateRecurringRule))
        ->for($team)
        ->using(transactionEntryAttributes($account, $currency, repeat: true)))
        ->toThrow(AuthorizationException::class, trans('Repeating a transaction requires the Pro plan.'));

    expect(RecurringRule::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});
