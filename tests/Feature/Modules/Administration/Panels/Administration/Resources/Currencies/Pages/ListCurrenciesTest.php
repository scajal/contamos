<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\ListCurrencies;
use App\Modules\Core\Catalog\Models\Currency;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages\ListCurrencies.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.currencies.index'))
        ->assertRedirectToRoute('login');
});

it('renders the currencies index for an authenticated member', function (): void {
    actingMember();

    $this->get(route('filament.administration.resources.currencies.index'))
        ->assertOk();
});

it('lists currencies', function (): void {
    actingMember();
    $currency = Currency::factory()->active()->fiat()->create(['name' => 'Peso', 'code' => 'ARS']);

    Livewire::test(ListCurrencies::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$currency]);
});

it('searches currencies by code', function (): void {
    actingMember();
    $matching = Currency::factory()->create(['name' => 'Peso', 'code' => 'ARS']);
    $other = Currency::factory()->create(['name' => 'Real', 'code' => 'BRL']);

    Livewire::test(ListCurrencies::class)
        ->loadTable()
        ->searchTable('ARS')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('activates the currency', function (): void {
    actingMember();
    $currency = Currency::factory()->inactive()->create();

    Livewire::test(ListCurrencies::class)
        ->loadTable()
        ->callAction(TestAction::make('activate')->table($currency))
        ->assertNotified(trans('Saved'));

    expect($currency->fresh()?->is_active)->toBeTrue();
});

it('deactivates the currency', function (): void {
    actingMember();
    $currency = Currency::factory()->active()->create();

    Livewire::test(ListCurrencies::class)
        ->loadTable()
        ->callAction(TestAction::make('deactivate')->table($currency))
        ->assertNotified(trans('Saved'));

    expect($currency->fresh()?->is_active)->toBeFalse();
});
