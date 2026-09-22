<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Pages\ListSubscriptions.
 */
it('redirects guests to login', function (): void {
    $this->get(route('filament.administration.resources.subscriptions.index'))
        ->assertRedirectToRoute('login');
});

it('lists the subscriptions of every team', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    subscribeToPro($team);

    Livewire::test(ListSubscriptions::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords(Subscription::query()->get());
});
