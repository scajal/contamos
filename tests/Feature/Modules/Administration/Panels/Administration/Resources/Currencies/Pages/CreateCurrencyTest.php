<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\CreateCurrency;
use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\CreateCurrency.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.currencies.create'))
        ->assertRedirectToRoute('login');
});

it('creates a currency with an uppercased code', function (): void {
    actingMember();

    Livewire::test(CreateCurrency::class)
        ->fillForm([
            'code' => 'ars',
            'decimal_places' => 2,
            'name' => 'Argentine Peso',
            'quote_source' => QuoteSource::ManualOrScheduled,
            'symbol' => '$',
            'type' => CurrencyType::Fiat,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas((new Currency)->getTable(), [
        'code' => 'ARS',
        'name' => 'Argentine Peso',
    ]);
});

it('rejects an empty payload', function (): void {
    actingMember();

    Livewire::test(CreateCurrency::class)
        ->fillForm([
            'code' => null,
            'decimal_places' => null,
            'name' => null,
            'quote_source' => null,
            'symbol' => null,
            'type' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['code', 'decimal_places', 'name', 'quote_source', 'symbol', 'type'])
        ->assertNotNotified()
        ->assertNoRedirect();
});

it('rejects a duplicate code', function (): void {
    actingMember();
    Currency::factory()->create(['code' => 'ARS']);

    Livewire::test(CreateCurrency::class)
        ->fillForm([
            'code' => 'ARS',
            'decimal_places' => 2,
            'name' => 'Argentine Peso',
            'quote_source' => QuoteSource::ManualOrScheduled,
            'symbol' => '$',
            'type' => CurrencyType::Fiat,
        ])
        ->call('create')
        ->assertHasFormErrors(['code'])
        ->assertNotNotified()
        ->assertNoRedirect();
});
