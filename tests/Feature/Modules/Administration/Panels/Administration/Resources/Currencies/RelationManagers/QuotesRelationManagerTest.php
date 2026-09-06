<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\EditCurrency;
use App\Modules\Administration\Panels\Administration\Resources\Currencies\RelationManagers\QuotesRelationManager;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Currencies\RelationManagers\QuotesRelationManager.
 */
it('lists quotes newest first', function (): void {
    actingMember();
    $currency = Currency::factory()->create();
    $older = Quote::factory()->for($currency)->create(['observed_at' => '2024-01-01 00:00:00']);
    $newer = Quote::factory()->for($currency)->create(['observed_at' => '2024-06-01 00:00:00']);

    Livewire::test(QuotesRelationManager::class, [
        'ownerRecord' => $currency,
        'pageClass' => EditCurrency::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('creates a quote with a forced manual source', function (): void {
    actingMember();
    $currency = Currency::factory()->create();

    Livewire::test(QuotesRelationManager::class, [
        'ownerRecord' => $currency,
        'pageClass' => EditCurrency::class,
    ])
        ->callAction(TestAction::make('create')->table(), [
            'observed_at' => '2024-01-01 12:00:00',
            'rate' => '1.25',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $this->assertDatabaseHas((new Quote)->getTable(), [
        $currency->quotes()->getForeignKeyName() => $currency->getKey(),
        'source' => QuoteSource::ManualOrScheduled->value,
        'rate' => '1.250000000000000000',
    ]);
});

it('rejects a missing rate and observed at', function (): void {
    actingMember();
    $currency = Currency::factory()->create();

    Livewire::test(QuotesRelationManager::class, [
        'ownerRecord' => $currency,
        'pageClass' => EditCurrency::class,
    ])
        ->callAction(TestAction::make('create')->table(), [
            'observed_at' => null,
            'rate' => null,
        ])
        ->assertHasFormErrors(['observed_at', 'rate']);
});
