<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\AccountCurrency;
use App\Modules\Core\Identity\Models\Team;
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
});
