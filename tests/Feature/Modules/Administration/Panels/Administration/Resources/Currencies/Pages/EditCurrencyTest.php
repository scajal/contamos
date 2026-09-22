<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\EditCurrency;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\EditCurrency.
 */
it('updates the currency name, symbol, and quote source', function (): void {
    actingOperator();
    $currency = Currency::factory()->fiat()->create([
        'code' => 'ARS',
        'name' => 'Old',
        'symbol' => 'x',
        'quote_source' => QuoteSource::ManualOrScheduled,
    ]);

    Livewire::test(EditCurrency::class, ['record' => $currency->getKey()])
        ->fillForm([
            'name' => 'Peso',
            'symbol' => '$',
            'quote_source' => QuoteSource::Live,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($currency->fresh()?->name)->toBe('Peso')
        ->and($currency->fresh()?->symbol)->toBe('$')
        ->and($currency->fresh()?->quote_source)->toBe(QuoteSource::Live)
        ->and($currency->fresh()?->code)->toBe('ARS');
});
