<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\TransactionController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Finance\Models\Team\Transaction.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function transactionPayload(array $overrides = []): array
{
    return [
        'amount' => '10.50',
        'description' => 'Lunch',
        'occurred_at' => '2024-06-01 12:00:00',
        'quote' => '1',
        'type' => TransactionType::Expense->value,
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred list of transactions', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Lunch',
        ]);

        $this->get(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/index')
                ->has('user')
                ->has('accounts')
                ->has('currencies')
                ->has('types')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $transaction->getKey())));
    });

    it('filters transactions by type, account, currency, search, and date range', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $otherCurrency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $otherAccount = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);
        $otherAccount->currencies()->attach($otherCurrency);

        $matching = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Office lunch',
            'occurred_at' => '2024-06-15 12:00:00',
        ]);
        Transaction::factory()->income()->create([
            'account_id' => $otherAccount->getKey(),
            'currency_id' => $otherCurrency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Salary',
            'occurred_at' => '2024-01-01 12:00:00',
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getKey(),
            'filter' => [
                'type' => TransactionType::Expense->value,
                'account' => $account->getKey(),
                'currency' => $currency->getKey(),
                'search' => 'lunch',
                'from' => '2024-06-01',
                'to' => '2024-06-30',
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.type', TransactionType::Expense->value)
                ->where('filters.account', $account->getKey())
                ->where('filters.currency', $currency->getKey())
                ->where('filters.search', 'lunch')
                ->where('filters.from', '2024-06-01')
                ->where('filters.to', '2024-06-30')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $matching->getKey())));
    });

    it('filters transactions by id through search', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $matching = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Lunch',
        ]);
        Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'description' => 'Dinner',
        ]);

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getKey(),
            'filter' => [
                'search' => $matching->getKey(),
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.search', $matching->getKey())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $matching->getKey())));
    });

    it('rejects a to date that is before from', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getKey(),
            'filter' => [
                'from' => '2024-06-30',
                'to' => '2024-06-01',
            ],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.to']);
    });

    it('rejects an account filter from another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Account::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.transactions.index', [
            'team' => $team->getKey(),
            'filter' => ['account' => $foreign->getKey()],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.account']);
    });
});

describe('create', function (): void {
    it('renders the create transaction modal for each type', function (TransactionType $type): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $inactiveCurrency = Currency::factory()->inactive()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach([$currency->getKey(), $inactiveCurrency->getKey()]);
        Account::factory()->bank()->inactive()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.create', [
                'team' => $team->getKey(),
                'type' => $type->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/create')
                ->has('accounts', 1)
                ->where('accounts.0.id', $account->getKey())
                ->has('accounts.0.currencies', 1)
                ->where('accounts.0.currencies.0.id', $currency->getKey())
                ->where('type', $type->value));
    })->with(TransactionType::cases());

    it('returns 404 for an unknown transaction type', function (): void {
        [$user, $team] = actingMember();

        $this->get('/teams/'.$team->getKey().'/transactions/create/transfer')
            ->assertNotFound();
    });
});

describe('store', function (): void {
    it('creates a transaction copying decimal places from the currency', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $transaction = Transaction::query()->sole();
        expect($transaction->decimal_places)->toBe(2)
            ->and($transaction->user?->is($user))->toBeTrue()
            ->and($transaction->type)->toBe(TransactionType::Expense);
    });

    it('defaults the quote from the currency current quote when omitted', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-01 00:00:00',
            'rate' => '1.5',
        ]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency);

        $payload = transactionPayload([
            $account->getForeignKey() => $account->getKey(),
            $currency->getForeignKey() => $currency->getKey(),
        ]);
        unset($payload['quote']);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getKey()]), $payload)
            ->assertRedirect();

        expect(Transaction::query()->sole()->quote)->toBe('1.500000000000000000');
    });

    it('rejects a currency that is not attached to the account', function (): void {
        [$user, $team] = actingMember();
        $attached = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $other = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($attached);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getKey()]), transactionPayload([
                $account->getForeignKey() => $account->getKey(),
                $other->getForeignKey() => $other->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid([$other->getForeignKey()]);
    });

    it('rejects an account from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();
        $foreign->currencies()->attach($currency);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getKey()]), transactionPayload([
                $foreign->getForeignKey() => $foreign->getKey(),
                $currency->getForeignKey() => $currency->getKey(),
            ]))
            ->assertRedirect()
            ->assertInvalid([$foreign->getForeignKey()]);
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.transactions.store', ['team' => $team->getKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['amount', 'occurred_at', 'quote', 'type']);
    });
});

describe('show', function (): void {
    it('renders the transaction modal', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.transactions.show', [
                'team' => $team->getKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/transactions/show')
                ->where('transaction.id', $transaction->getKey()));
    });

    it('returns 404 when the transaction belongs to another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Transaction::factory()->create();

        $this->get(route('finance.teams.transactions.show', [
            'team' => $team->getKey(),
            'transaction' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('deletes the transaction', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($currency, ['balance' => '75.00']);
        $transaction = Transaction::factory()->expense()->create([
            'account_id' => $account->getKey(),
            'amount' => '25.00',
            'currency_id' => $currency->getKey(),
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->from(route('finance.teams.transactions.index', ['team' => $team->getKey()]))
            ->delete(route('finance.teams.transactions.destroy', [
                'team' => $team->getKey(),
                'transaction' => $transaction->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));
        $this->assertModelMissing($transaction);
        expect((float) $account->currencies()->firstOrFail()->pivot->balance)->toBe(100.0);
    });
});
