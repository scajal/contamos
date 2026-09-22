<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Customers\Pages\ListCustomers;
use App\Modules\Billing\Models\Customer;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Customers\Pages\ListCustomers.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.customers.index'))
        ->assertRedirectToRoute('login');
});

it('lists the billing customers', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $customer = $team->customer()->create([
        'email' => 'billing@example.com',
        'name' => $team->name,
        'paddle_id' => 'ctm_'.fake()->uuid(),
    ]);

    expect($customer)->toBeInstanceOf(Customer::class);

    Livewire::test(ListCustomers::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$customer]);
});
