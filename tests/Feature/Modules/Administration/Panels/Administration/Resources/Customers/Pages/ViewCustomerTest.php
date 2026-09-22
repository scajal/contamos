<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Billing\Models\Customer;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Customers\Pages\ViewCustomer.
 */
it('renders the billing customer', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $customer = $team->customer()->create([
        'email' => 'billing@example.com',
        'name' => $team->name,
        'paddle_id' => 'ctm_'.fake()->uuid(),
    ]);

    expect($customer)->toBeInstanceOf(Customer::class);

    Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertOk()
        ->assertSee('billing@example.com');
});
