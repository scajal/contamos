<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Catalog\Http\Controllers\Currency\QuoteController.
 */
describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $currency = Currency::factory()->create();

        $this->get(route('catalog.currencies.quotes.index', ['currency' => $currency->getKey()]))
            ->assertRedirectToRoute('login');
    });

    it('renders a deferred list of quotes newest first', function (): void {
        actingMember();
        $currency = Currency::factory()->create();
        $older = Quote::factory()->for($currency)->create(['observed_at' => '2024-01-01 00:00:00']);
        $newer = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00']);
        $older->forceFill(['created_at' => '2024-01-01 00:00:00'])->save();
        $newer->forceFill(['created_at' => '2024-06-01 00:00:00'])->save();

        $this->get(route('catalog.currencies.quotes.index', ['currency' => $currency->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('catalog/currencies/quotes/index')
                ->where('currency.id', $currency->getKey())
                ->has('filters')
                ->has('sources')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('quotes.data', 2)
                    ->where('quotes.data.0.id', $newer->getKey())));
    });

    it('filters quotes by source and search', function (): void {
        actingMember();
        $currency = Currency::factory()->create();
        $matching = Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-01 12:00:00',
            'source' => QuoteSource::ManualOrScheduled,
        ]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-06-01 12:00:00',
            'source' => QuoteSource::Live,
        ]);
        Quote::factory()->for($currency)->create([
            'observed_at' => '2024-01-01 12:00:00',
            'source' => QuoteSource::ManualOrScheduled,
        ]);
        Quote::factory()->create([
            'observed_at' => '2024-06-01 12:00:00',
            'source' => QuoteSource::ManualOrScheduled,
        ]);

        $this->get(route('catalog.currencies.quotes.index', [
            'currency' => $currency->getKey(),
            'filter' => [
                'search' => '2024-06-01',
                'source' => QuoteSource::ManualOrScheduled->value,
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.search', '2024-06-01')
                ->where('filters.source', QuoteSource::ManualOrScheduled->value)
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('quotes.data', 1)
                    ->where('quotes.data.0.id', $matching->getKey())));
    });

    it('rejects an invalid source filter', function (): void {
        actingMember();
        $currency = Currency::factory()->create();

        $this->get(route('catalog.currencies.quotes.index', [
            'currency' => $currency->getKey(),
            'filter' => ['source' => 'not-a-source'],
        ]))
            ->assertRedirect()
            ->assertInvalid([
                'filter.source' => trans('validation.enum', ['attribute' => 'filter.source']),
            ]);
    });
});

describe('create', function (): void {
    it('renders the create quote modal', function (): void {
        actingMember();
        $currency = Currency::factory()->create();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('catalog.currencies.quotes.create', ['currency' => $currency->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('catalog/currencies/quotes/create')
                ->where('currency.id', $currency->getKey()));
    });
});

describe('store', function (): void {
    it('creates a quote with a forced manual source', function (): void {
        actingMember();
        $currency = Currency::factory()->create();

        $this->from(route('catalog.currencies.quotes.index', ['currency' => $currency->getKey()]))
            ->post(route('catalog.currencies.quotes.store', ['currency' => $currency->getKey()]), [
                'observed_at' => '2024-01-01 12:00:00',
                'rate' => '1.25',
                'source' => QuoteSource::Live->value,
            ])
            ->assertRedirect();

        assertFlashedToast(trans('Saved'));
        $this->assertDatabaseHas((new Quote)->getTable(), [
            $currency->quotes()->getForeignKeyName() => $currency->getKey(),
            'source' => QuoteSource::ManualOrScheduled->value,
            'rate' => '1.250000000000000000',
        ]);
    });

    it('rejects a missing rate and observed at', function (): void {
        actingMember();
        $currency = Currency::factory()->create();

        $this->from(route('catalog.currencies.quotes.index', ['currency' => $currency->getKey()]))
            ->post(route('catalog.currencies.quotes.store', ['currency' => $currency->getKey()]), [])
            ->assertRedirect()
            ->assertInvalid(['observed_at', 'rate']);
    });
});

describe('destroy', function (): void {
    it('deletes the quote', function (): void {
        actingMember();
        $currency = Currency::factory()->create();
        $quote = Quote::factory()->for($currency)->create();

        $this->from(route('catalog.currencies.quotes.index', ['currency' => $currency->getKey()]))
            ->delete(route('catalog.currencies.quotes.destroy', [
                'currency' => $currency->getKey(),
                'quote' => $quote->getKey(),
            ]))
            ->assertRedirect();

        assertFlashedToast(trans('Deleted'));
        $this->assertModelMissing($quote);
    });

    it('returns 404 when the quote belongs to another currency', function (): void {
        actingMember();
        $currency = Currency::factory()->create();
        $other = Currency::factory()->create();
        $quote = Quote::factory()->for($other)->create();

        $this->delete(route('catalog.currencies.quotes.destroy', [
            'currency' => $currency->getKey(),
            'quote' => $quote->getKey(),
        ]))->assertNotFound();

        $this->assertModelExists($quote);
    });
});
