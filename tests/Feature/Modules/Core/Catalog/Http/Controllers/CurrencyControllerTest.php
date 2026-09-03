<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Catalog\Http\Controllers\CurrencyController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Catalog\Models\Currency.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function currencyPayload(array $overrides = []): array
{
    return [
        'code' => 'ars',
        'decimal_places' => 2,
        'name' => 'Argentine Peso',
        'quote_source' => QuoteSource::Manual->value,
        'symbol' => '$',
        'type' => CurrencyType::Fiat->value,
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('catalog.currencies.index'))
            ->assertRedirectToRoute('login');
    });

    it('renders a deferred list of currencies', function (): void {
        actingMember();
        Currency::factory()->active()->fiat()->create(['name' => 'Peso', 'code' => 'ARS']);

        $this->get(route('catalog.currencies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('catalog/currencies/index')
                ->has('user')
                ->has('types')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('currencies.data', 1)
                    ->where('currencies.data.0.code', 'ARS')));
    });

    it('filters currencies by active state, type, and search', function (): void {
        actingMember();
        Currency::factory()->active()->fiat()->create(['name' => 'Peso', 'code' => 'ARS']);
        Currency::factory()->inactive()->fiat()->create(['name' => 'Real', 'code' => 'BRL']);
        Currency::factory()->active()->crypto()->create(['name' => 'Bitcoin', 'code' => 'BTC']);

        $this->get(route('catalog.currencies.index', [
            'filter' => [
                'is_active' => 'true',
                'type' => CurrencyType::Fiat->value,
                'search' => 'ARS',
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.is_active', 'true')
                ->where('filters.type', CurrencyType::Fiat->value)
                ->where('filters.search', 'ARS')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('currencies.data', 1)
                    ->where('currencies.data.0.code', 'ARS')));
    });

    it('rejects an invalid type filter', function (): void {
        actingMember();

        $this->get(route('catalog.currencies.index', ['filter' => ['type' => 'not-a-type']]))
            ->assertRedirect()
            ->assertInvalid([
                'filter.type' => trans('validation.enum', ['attribute' => 'filter.type']),
            ]);
    });

    it('paginates currencies in pages of ten', function (): void {
        actingMember();
        Currency::factory()->count(11)->create();

        $this->get(route('catalog.currencies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('currencies.data', 10)
                    ->where('currencies.last_page', 2)));
    });
});

describe('create', function (): void {
    it('renders the create currency modal', function (): void {
        actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('catalog.currencies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('catalog/currencies/create')
                ->has('quoteSources')
                ->has('types'));
    });
});

describe('store', function (): void {
    it('redirects guests to login', function (): void {
        $this->post(route('catalog.currencies.store'), currencyPayload())
            ->assertRedirectToRoute('login');
    });

    it('creates a currency with an uppercased code', function (): void {
        actingMember();

        $this->from(route('catalog.currencies.index'))
            ->post(route('catalog.currencies.store'), currencyPayload())
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        $this->assertDatabaseHas((new Currency)->getTable(), [
            'code' => 'ARS',
            'name' => 'Argentine Peso',
        ]);
    });

    it('rejects an empty payload', function (): void {
        actingMember();

        $this->from(route('catalog.currencies.index'))
            ->post(route('catalog.currencies.store'), [])
            ->assertRedirect()
            ->assertInvalid(['code', 'decimal_places', 'name', 'quote_source', 'symbol', 'type']);
    });

    it('rejects a duplicate code', function (): void {
        actingMember();
        Currency::factory()->create(['code' => 'ARS']);

        $this->from(route('catalog.currencies.index'))
            ->post(route('catalog.currencies.store'), currencyPayload(['code' => 'ARS']))
            ->assertRedirect()
            ->assertInvalid([
                'code' => trans('validation.unique', ['attribute' => trans('validation.attributes.code')]),
            ]);
    });

    it('rejects decimal places above 18', function (): void {
        actingMember();

        $this->from(route('catalog.currencies.index'))
            ->post(route('catalog.currencies.store'), currencyPayload(['decimal_places' => 19]))
            ->assertRedirect()
            ->assertInvalid(['decimal_places']);
    });
});

describe('edit', function (): void {
    it('renders the edit currency modal', function (): void {
        actingMember();
        $currency = Currency::factory()->fiat()->create(['code' => 'ARS']);

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('catalog.currencies.edit', ['currency' => $currency->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('catalog/currencies/edit')
                ->where('currency.id', $currency->getKey())
                ->has('quoteSources')
                ->has('types'));
    });
});

describe('update', function (): void {
    it('updates the currency name, symbol, and quote source', function (): void {
        actingMember();
        $currency = Currency::factory()->fiat()->create([
            'code' => 'ARS',
            'name' => 'Old',
            'symbol' => 'x',
            'quote_source' => QuoteSource::Manual,
        ]);

        $this->from(route('catalog.currencies.index'))
            ->patch(route('catalog.currencies.update', ['currency' => $currency->getKey()]), [
                'name' => 'Peso',
                'symbol' => '$',
                'quote_source' => QuoteSource::Live->value,
            ])
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($currency->fresh()?->name)->toBe('Peso')
            ->and($currency->fresh()?->symbol)->toBe('$')
            ->and($currency->fresh()?->quote_source)->toBe(QuoteSource::Live)
            ->and($currency->fresh()?->code)->toBe('ARS');
    });
});

describe('activate', function (): void {
    it('activates the currency', function (): void {
        actingMember();
        $currency = Currency::factory()->inactive()->create();

        $this->from(route('catalog.currencies.index'))
            ->patch(route('catalog.currencies.activate', ['currency' => $currency->getKey()]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($currency->fresh()?->is_active)->toBeTrue();
    });
});

describe('deactivate', function (): void {
    it('deactivates the currency', function (): void {
        actingMember();
        $currency = Currency::factory()->active()->create();

        $this->from(route('catalog.currencies.index'))
            ->patch(route('catalog.currencies.deactivate', ['currency' => $currency->getKey()]))
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        expect($currency->fresh()?->is_active)->toBeFalse();
    });
});
