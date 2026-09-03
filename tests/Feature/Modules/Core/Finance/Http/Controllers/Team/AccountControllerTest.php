<?php

declare(strict_types=1);

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

        $this->get(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/accounts/index')
                ->has('user')
                ->has('types')
                ->has('filters')
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
            'team' => $team->getKey(),
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
            'team' => $team->getKey(),
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
            ->get(route('finance.teams.accounts.create', ['team' => $team->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('finance/teams/accounts/create')
                ->has('currencies', 1)
                ->where('currencies.0.id', $active->getKey())
                ->has('types'));
    });
});

describe('store', function (): void {
    it('creates an account and syncs currencies', function (): void {
        [$user, $team] = actingMember();
        $currency = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getKey()]), accountPayload([
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

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getKey()]), accountPayload([
                'type' => AccountType::Bank->value,
                'currency_ids' => [$crypto->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_ids.0']);
    });

    it('rejects a fiat currency on a crypto account', function (): void {
        [$user, $team] = actingMember();
        $fiat = Currency::factory()->active()->fiat()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getKey()]), accountPayload([
                'type' => AccountType::Crypto->value,
                'currency_ids' => [$fiat->getKey()],
            ]))
            ->assertRedirect()
            ->assertInvalid(['currency_ids.0']);
    });

    it('accepts a cryptocurrency on a crypto account', function (): void {
        [$user, $team] = actingMember();
        $crypto = Currency::factory()->active()->crypto()->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getKey()]), accountPayload([
                'type' => AccountType::Crypto->value,
                'currency_ids' => [$crypto->getKey()],
            ]))
            ->assertRedirect();

        expect(Account::query()->sole()->type)->toBe(AccountType::Crypto);
    });

    it('rejects an empty payload', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->post(route('finance.teams.accounts.store', ['team' => $team->getKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['currency_ids', 'name', 'type']);
    });
});

describe('edit', function (): void {
    it('renders the edit account modal', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->bank()->for($team)->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('finance.teams.accounts.edit', [
                'team' => $team->getKey(),
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
            'team' => $team->getKey(),
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

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->put(route('finance.teams.accounts.update', [
                'team' => $team->getKey(),
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

        $this->put(route('finance.teams.accounts.update', [
            'team' => $team->getKey(),
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

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.accounts.activate', [
                'team' => $team->getKey(),
                'account' => $account->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($account->fresh()?->is_active)->toBeTrue();
    });
});

describe('deactivate', function (): void {
    it('deactivates the account', function (): void {
        [$user, $team] = actingMember();
        $account = Account::factory()->active()->for($team)->create();

        $this->from(route('finance.teams.accounts.index', ['team' => $team->getKey()]))
            ->patch(route('finance.teams.accounts.deactivate', [
                'team' => $team->getKey(),
                'account' => $account->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($account->fresh()?->is_active)->toBeFalse();
    });
});
