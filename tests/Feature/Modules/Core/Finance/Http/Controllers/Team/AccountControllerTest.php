<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\AccountCurrency;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Finance\Http\Controllers\Team\AccountController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Finance\Models\Team\Account.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountPayload(array $overrides = []): array
{
    return [
        'name' => 'Checking',
        'type' => AccountType::Bank->value,
        'currency_ids' => [],
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('renders a deferred list of accounts', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);
        $account->currencies()->attach($currency);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/accounts/index')
                ->has('user')
                ->has('types')
                ->has('filters')
                ->has('currencies', 1)
                ->where('currencies.0.id', $currency->getKey())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('accounts.data', 1)
                    ->where('accounts.data.0.name', 'Checking')));
    });

    it('filters accounts by type, activity, search, and currency', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $real = Currency::factory()->active()->fiat()->create(['code' => 'BRL']);
        $matching = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);
        $matching->currencies()->attach($peso);
        $other = Account::factory()->cash()->inactive()->for($team)->create(['name' => 'Wallet']);
        $other->currencies()->attach($real);

        $this->get(route('finance.teams.accounts.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'type' => AccountType::Bank->value,
                'is_active' => 'true',
                'search' => 'Check',
                'currency' => $peso->getKey(),
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.type', AccountType::Bank->value)
                ->where('filters.is_active', 'true')
                ->where('filters.search', 'Check')
                ->where('filters.currency', $peso->getKey())
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('accounts.data', 1)
                    ->where('accounts.data.0.id', $matching->getKey())));
    });

    it('renders the signed balance per currency of each account', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2, 'symbol' => '$']);
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2, 'symbol' => 'US$']);
        $checking = Account::factory()->bank()->active()->for($team)->create(['created_at' => now()->subDay()]);
        $checking->currencies()->attach([$peso->getKey(), $dollar->getKey()]);
        $wallet = Account::factory()->cash()->active()->for($team)->create(['created_at' => now()]);
        $wallet->currencies()->attach($peso);

        Transaction::factory()->income()->for($checking)->for($peso)->create(['amount' => '100.50']);
        Transaction::factory()->expense()->for($checking)->for($peso)->create(['amount' => '30.25']);
        Transaction::factory()->expense()->for($checking)->for($dollar)->create(['amount' => '10']);
        Transaction::factory()->income()->for($wallet)->for($peso)->create(['amount' => '40']);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('accounts.data', 2)
                    ->where('accounts.data.0.id', $wallet->getKey())
                    ->has('accounts.data.0.balances', 1)
                    ->where('accounts.data.0.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 40.0)
                    ->where('accounts.data.1.id', $checking->getKey())
                    ->has('accounts.data.1.balances', 2)
                    ->where('accounts.data.1.balances.0.id', $peso->getKey())
                    ->where('accounts.data.1.balances.0.code', 'ARS')
                    ->where('accounts.data.1.balances.0.symbol', '$')
                    ->where('accounts.data.1.balances.0.decimal_places', 2)
                    ->where('accounts.data.1.balances.0.balance', fn (mixed $balance): bool => is_string($balance) && (float) $balance === 70.25)
                    ->where('accounts.data.1.balances.1.code', 'USD')
                    ->where('accounts.data.1.balances.1.balance', fn (mixed $balance): bool => (float) $balance === -10.0)));
    });

    it('adds an opening balance to the account balance', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($peso);

        Transaction::factory()->openingBalance()->for($account)->for($peso)->create(['amount' => '100']);
        Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '30']);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('accounts.data.0.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 70.0)));
    });

    it('counts a transfer and its fees on both accounts', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $origin = Account::factory()->bank()->active()->for($team)->create(['created_at' => now()->subDay()]);
        $origin->currencies()->attach($peso);
        $destination = Account::factory()->cash()->active()->for($team)->create(['created_at' => now()]);
        $destination->currencies()->attach($peso);
        $transfer = Transfer::factory()->for($team)->create();

        Transaction::factory()->openingBalance()->for($origin)->for($peso)->create(['amount' => '100']);
        Transaction::factory()->transferOut()->for($origin)->for($peso)->for($transfer)->create(['amount' => '25']);
        Transaction::factory()->transferIn()->for($destination)->for($peso)->for($transfer)->create(['amount' => '25']);
        Transaction::factory()->expense()->for($origin)->for($peso)->for($transfer)->create(['amount' => '1.50']);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('accounts.data.0.id', $destination->getKey())
                    ->where('accounts.data.0.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 25.0)
                    ->where('accounts.data.1.id', $origin->getKey())
                    ->where('accounts.data.1.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 73.5)));
    });

    it('counts transactions older than the free window in the balance', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($peso);

        Transaction::factory()->income()->for($account)->for($peso)->create([
            'amount' => '500',
            'occurred_at' => CarbonImmutable::now()->startOfMonth()->subMonths(5)->subDay(),
        ]);
        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100']);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->where('accounts.data.0.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 600.0)));
    });

    it('renders balances of an inactive account and zero for a held currency with no transactions', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD']);
        $account = Account::factory()->cash()->inactive()->for($team)->create();
        $account->currencies()->attach([$peso->getKey(), $dollar->getKey()]);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '500']);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('accounts.data.0.balances', 2)
                    ->where('accounts.data.0.balances.0.code', 'ARS')
                    ->where('accounts.data.0.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 500.0)
                    ->where('accounts.data.0.balances.1.code', 'USD')
                    ->where('accounts.data.0.balances.1.balance', '0')));
    });

    it('does not count another team\'s transactions', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        $account = Account::factory()->bank()->active()->for($team)->create();
        $account->currencies()->attach($peso);
        $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();
        $foreign->currencies()->attach($peso);

        Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100']);
        Transaction::factory()->income()->for($foreign)->for($peso)->create(['amount' => '900']);

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('accounts.data', 1)
                    ->where('accounts.data.0.balances.0.balance', fn (mixed $balance): bool => (float) $balance === 100.0)));
    });

    it('rejects an invalid type filter', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('finance.teams.accounts.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['type' => 'not-a-type'],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.type']);
    });
});

describe('create', function (): void {
    it('renders the create account modal with active currencies', function (): void {
        [$user, $team] = actingMember();
        $active = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
        Currency::factory()->inactive()->fiat()->create(['code' => 'BRL']);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.accounts.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/accounts/create')
                ->has('currencies', 1)
                ->where('currencies.0.id', $active->getKey())
                ->has('types'));
    });
});

describe('store', function (): void {
    it('redirects to the upgrade modal when the free plan account limit is reached', function (): void {
        [$user, $team] = actingMember();
        Account::factory()->count(3)->active()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload())
            ->assertRedirectToRoute('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::AddAccounts->value,
            ]);

        expect(Account::query()->count())->toBe(3);
    });

    it('lets a pro team create accounts beyond the free limit', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $currency = Currency::factory()->active()->fiat()->create();
        Account::factory()->count(3)->active()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$currency->getKey()],
            ]))
            ->assertRedirect(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]));

        expect(Account::query()->count())->toBe(4);
    });

    it('creates an account and syncs currencies', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$currency->getKey()],
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));

        $account = Account::query()->sole();
        expect($account->name)->toBe('Checking')
            ->and($account->type)->toBe(AccountType::Bank)
            ->and($account->currencies()->pluck($currency->qualifyColumn($currency->getKeyName()))->all())->toBe([$currency->getKey()]);
    });

    it('rejects a crypto currency on a bank account', function (): void {
        [$user, $team] = actingMember();
        $crypto = Currency::factory()->active()->crypto()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'type' => AccountType::Bank->value,
                'currency_ids' => [$crypto->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_ids.0']);
    });

    it('rejects a fiat currency on a crypto account', function (): void {
        [$user, $team] = actingMember();
        $fiat = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'type' => AccountType::Crypto->value,
                'currency_ids' => [$fiat->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_ids.0']);
    });

    it('accepts a cryptocurrency on a crypto account', function (): void {
        [$user, $team] = actingMember();
        $crypto = Currency::factory()->active()->crypto()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'type' => AccountType::Crypto->value,
                'currency_ids' => [$crypto->getKey()],
            ]))
            ->assertRedirect();

        expect(Account::query()->sole()->type)->toBe(AccountType::Crypto);
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['currency_ids', 'name', 'type']);
    });

    it('rejects currency ids that are not an array', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => 'not-an-array',
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_ids']);

        expect(Account::query()->count())->toBe(0);
    });

    it('rejects an opening balance for a currency with no quote and no user-typed rate', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$currency->getKey()],
                'opening_balances' => [$currency->getKey() => '100'],
            ]))
            ->assertRedirect()
            ->assertInvalid(["opening_balances.{$currency->getKey()}"]);

        expect(Account::query()->count())->toBe(0);
    });

    it('creates an opening balance using a user-typed rate when no catalog quote exists', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$currency->getKey()],
                'opening_balances' => [$currency->getKey() => '100'],
                'quotes' => [$currency->getKey() => '1.5'],
            ]))
            ->assertRedirect();

        $account = Account::query()->sole();
        $transaction = $account->transactions()->sole();

        expect((float) $transaction->quote)->toBe(1.5)
            ->and((float) $transaction->amount)->toBe(100.0);
    });

    it('creates one opening balance row per currency, resolving the catalog quote and recording the user', function (): void {
        [$user, $team] = actingMember();
        $peso = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $dollar = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($peso)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1.5']);
        Quote::factory()->for($dollar)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '1']);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$peso->getKey(), $dollar->getKey()],
                'occurred_at' => '2024-06-01T12:00:00',
                'opening_balances' => [
                    $peso->getKey() => '100.50',
                    $dollar->getKey() => '25',
                ],
                'timezone' => 'UTC',
            ]))
            ->assertRedirect();

        $account = Account::query()->sole();

        expect($account->transactions()->count())->toBe(2);

        $pesoTransaction = $account->transactions()->where('currency_id', $peso->getKey())->sole();
        expect($pesoTransaction->type)->toBe(TransactionType::OpeningBalance)
            ->and((float) $pesoTransaction->amount)->toBe(100.5)
            ->and($pesoTransaction->decimal_places)->toBe(2)
            ->and((float) $pesoTransaction->quote)->toBe(1.5)
            ->and($pesoTransaction->occurred_at->toIso8601String())->toBe('2024-06-01T12:00:00+00:00')
            ->and($pesoTransaction->user_id)->toBe($user->getKey())
            ->and((float) $account->currencies()->where('currency_id', $peso->getKey())->sole()->pivot->balance)->toBe(100.5);

        $dollarTransaction = $account->transactions()->where('currency_id', $dollar->getKey())->sole();
        expect((float) $dollarTransaction->amount)->toBe(25.0)
            ->and((float) $account->currencies()->where('currency_id', $dollar->getKey())->sole()->pivot->balance)->toBe(25.0);
    });

    it('creates nothing for an empty or zero opening balance amount', function (): void {
        [$user, $team] = actingMember();
        $empty = Currency::factory()->active()->fiat()->create();
        $zero = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$empty->getKey(), $zero->getKey()],
                'opening_balances' => [
                    $empty->getKey() => '',
                    $zero->getKey() => '0',
                ],
            ]))
            ->assertRedirect();

        $account = Account::query()->sole();
        expect($account->transactions()->count())->toBe(0);
    });

    it('rejects a negative opening balance', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$currency->getKey()],
                'opening_balances' => [$currency->getKey() => '-10'],
            ]))
            ->assertRedirect()
            ->assertInvalid(["opening_balances.{$currency->getKey()}"]);

        expect(Account::query()->count())->toBe(0);
    });

    it('rejects an opening balance with more decimals than the currency allows', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        Quote::factory()->for($currency)->create(['observed_at' => now()->subDay(), 'rate' => '1']);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$currency->getKey()],
                'opening_balances' => [$currency->getKey() => '10.001'],
            ]))
            ->assertRedirect()
            ->assertInvalid(["opening_balances.{$currency->getKey()}"]);

        expect(Account::query()->count())->toBe(0);
    });

    it('rejects an opening balance for a currency that is not in the submitted currency ids', function (): void {
        [$user, $team] = actingMember();
        $selected = Currency::factory()->active()->fiat()->create();
        $other = Currency::factory()->active()->fiat()->create();
        Quote::factory()->for($other)->create(['observed_at' => now()->subDay(), 'rate' => '1']);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
                'currency_ids' => [$selected->getKey()],
                'opening_balances' => [$other->getKey() => '10'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['opening_balances']);

        expect(Account::query()->count())->toBe(0);
    });

    it('records the account and its currencies as one entry in the activity log', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        clearActivities();

        $this->post(route('finance.teams.accounts.store', ['team' => $team->getRouteKey()]), accountPayload([
            'currency_ids' => [$currency->getKey()],
        ]))->assertRedirect();

        $account = Account::query()->sole();
        $activities = activitiesOf($team);

        expect($activities->pluck('event')->all())->toBe(['created', 'currency_attached'])
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->first()?->causer?->is($user))->toBeTrue()
            ->and($activities->first()?->subject?->is($account))->toBeTrue()
            ->and($activities->first()?->attribute_changes['attributes']['name'])->toBe('Checking')
            ->and($activities->last()?->subject?->is($account))->toBeTrue()
            ->and($activities->last()?->attribute_changes['attributes'])->toBe([$currency->getForeignKey() => $currency->getKey()]);
    });
});

describe('edit', function (): void {
    it('renders the edit account modal', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.accounts.edit', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/accounts/edit')
                ->where('account.id', $account->getKey())
                ->has('currencies')
                ->has('types'));
    });

    it('returns 404 when the account belongs to another team', function (): void {
        [$user, $team] = actingMember();
        $foreign = Account::factory()->for(Team::factory())->create();

        $this->get(route('finance.teams.accounts.edit', [
            'team' => $team->getRouteKey(),
            'account' => $foreign->getKey(),
        ]))->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates the account and syncs currencies', function (): void {
        [$user, $team] = actingMember();
        $old = Currency::factory()->active()->fiat()->create();
        $new = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->for($team)->create(['name' => 'Old']);
        $account->currencies()->attach($old);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.update', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]), accountPayload([
                'name' => 'New',
                'currency_ids' => [$new->getKey()],
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($account->fresh()?->name)->toBe('New')
            ->and($account->currencies()->pluck($new->qualifyColumn($new->getKeyName()))->all())->toBe([$new->getKey()])
            ->and($account->currencies()->first()?->pivot)->toBeInstanceOf(AccountCurrency::class);
    });

    it('returns 404 when updating an account from another team', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $foreign = Account::factory()->for(Team::factory())->create();

        $this->patch(route('finance.teams.accounts.update', [
            'team' => $team->getRouteKey(),
            'account' => $foreign->getKey(),
        ]), accountPayload([
            'currency_ids' => [$currency->getKey()],
        ]))->assertNotFound();
    });

    it('rejects currency ids that are not an array', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->for($team)->create(['name' => 'Checking']);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.update', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]), accountPayload([
                'currency_ids' => 'not-an-array',
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_ids']);

        expect($account->fresh()?->name)->toBe('Checking');
    });

    it('creates an opening balance row only for the newly attached currency', function (): void {
        [$user, $team] = actingMember();
        $old = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $new = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
        $account = Account::factory()->bank()->for($team)->create();
        $account->currencies()->attach($old);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.update', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]), accountPayload([
                'currency_ids' => [$old->getKey(), $new->getKey()],
                'opening_balances' => [$new->getKey() => '50'],
                'quotes' => [$new->getKey() => '1'],
            ]))
            ->assertRedirect();

        expect($account->transactions()->count())->toBe(1);

        $transaction = $account->transactions()->sole();
        expect($transaction->currency_id)->toBe($new->getKey())
            ->and((float) $transaction->amount)->toBe(50.0)
            ->and((float) $account->currencies()->where('currency_id', $new->getKey())->sole()->pivot->balance)->toBe(50.0);
    });

    it('rejects an opening balance keyed by an already attached currency', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->for($team)->create();
        $account->currencies()->attach($currency);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.update', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]), accountPayload([
                'currency_ids' => [$currency->getKey()],
                'opening_balances' => [$currency->getKey() => '50'],
                'quotes' => [$currency->getKey() => '1'],
            ]))
            ->assertRedirect()
            ->assertInvalid(['opening_balances']);

        expect($account->transactions()->count())->toBe(0);
    });

    it('leaves existing opening balance rows untouched', function (): void {
        [$user, $team] = actingMember();
        $old = Currency::factory()->active()->fiat()->create();
        $new = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->for($team)->create();
        $account->currencies()->attach($old, ['balance' => '75']);
        $existing = Transaction::factory()->openingBalance()->for($account)->for($old)->create(['amount' => '75']);

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.update', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]), accountPayload([
                'currency_ids' => [$old->getKey(), $new->getKey()],
                'opening_balances' => [$new->getKey() => '25'],
                'quotes' => [$new->getKey() => '1'],
            ]))
            ->assertRedirect();

        expect($existing->fresh()?->amount)->toBe($existing->amount)
            ->and((float) $account->currencies()->where('currency_id', $old->getKey())->sole()->pivot->balance)->toBe(75.0)
            ->and($account->transactions()->where('currency_id', $old->getKey())->count())->toBe(1);
    });

    it('records the rename and the currencies that came and went in the activity log', function (): void {
        [$user, $team] = actingMember();
        $old = Currency::factory()->active()->fiat()->create();
        $new = Currency::factory()->active()->fiat()->create();
        $account = Account::factory()->bank()->for($team)->create(['name' => 'Old']);
        $account->currencies()->attach($old);
        clearActivities();

        $this->patch(route('finance.teams.accounts.update', [
            'team' => $team->getRouteKey(),
            'account' => $account->getKey(),
        ]), accountPayload([
            'name' => 'New',
            'currency_ids' => [$new->getKey()],
        ]))->assertRedirect();

        $activities = activitiesOf($team);

        expect($activities->pluck('event')->all())->toBe(['updated', 'currency_attached', 'currency_detached'])
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->first()?->attribute_changes['old'])->toBe(['name' => 'Old'])
            ->and($activities->first()?->attribute_changes['attributes'])->toBe(['name' => 'New'])
            ->and($activities->get(1)?->attribute_changes['attributes'])->toBe([$new->getForeignKey() => $new->getKey()])
            ->and($activities->last()?->attribute_changes['old'])->toBe([$old->getForeignKey() => $old->getKey()]);
    });
});

describe('activate', function (): void {
    it('activates the account', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.activate', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($account->fresh()?->is_active)->toBeTrue();
    });

    it('redirects to the upgrade modal when reactivating past the free plan account limit', function (): void {
        [$user, $team] = actingMember();
        Account::factory()->count(3)->active()->for($team)->create();
        $account = Account::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.activate', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]))
            ->assertRedirectToRoute('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::AddAccounts->value,
            ]);

        expect($account->fresh()?->is_active)->toBeFalse();
    });

    it('lets a pro team reactivate beyond the free limit', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        Account::factory()->count(3)->active()->for($team)->create();
        $account = Account::factory()->inactive()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.activate', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]))
            ->assertRedirect(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]));

        expect($account->fresh()?->is_active)->toBeTrue();
    });

    it('records the activation in the activity log', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->inactive()->for($team)->create();
        clearActivities();

        $this->patch(route('finance.teams.accounts.activate', [
            'team' => $team->getRouteKey(),
            'account' => $account->getKey(),
        ]))->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('updated')
            ->and($activity->attribute_changes['old'])->toBe(['is_active' => false])
            ->and($activity->attribute_changes['attributes'])->toBe(['is_active' => true]);
    });
});

describe('deactivate', function (): void {
    it('deactivates the account', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getRouteKey()]))
            ->patch(route('finance.teams.accounts.deactivate', [
                'team' => $team->getRouteKey(),
                'account' => $account->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($account->fresh()?->is_active)->toBeFalse();
    });

    it('records the deactivation in the activity log', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->active()->for($team)->create();
        clearActivities();

        $this->patch(route('finance.teams.accounts.deactivate', [
            'team' => $team->getRouteKey(),
            'account' => $account->getKey(),
        ]))->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('updated')
            ->and($activity->attribute_changes['old'])->toBe(['is_active' => true])
            ->and($activity->attribute_changes['attributes'])->toBe(['is_active' => false]);
    });
});
