<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\ViewCurrency;
use App\Modules\Core\Catalog\Models\Currency;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\ViewCurrency.
 */
it('redirects guests to login', function (): void {
    $currency = Currency::factory()->create();

    $this->get(route('filament.administration.resources.currencies.view', ['record' => $currency->getKey()]))
        ->assertRedirectToRoute('login');
});

it('renders the currency', function (): void {
    actingMember();
    $currency = Currency::factory()->active()->fiat()->create(['name' => 'Peso', 'code' => 'ARS']);

    Livewire::test(ViewCurrency::class, ['record' => $currency->getKey()])
        ->assertOk();
});

it('activates the currency', function (): void {
    actingMember();
    $currency = Currency::factory()->inactive()->create();

    Livewire::test(ViewCurrency::class, ['record' => $currency->getKey()])
        ->callAction(TestAction::make('activate'))
        ->assertNotified(trans('Saved'));

    expect($currency->fresh()?->is_active)->toBeTrue();
});

it('deactivates the currency', function (): void {
    actingMember();
    $currency = Currency::factory()->active()->create();

    Livewire::test(ViewCurrency::class, ['record' => $currency->getKey()])
        ->callAction(TestAction::make('deactivate'))
        ->assertNotified(trans('Saved'));

    expect($currency->fresh()?->is_active)->toBeFalse();
});
