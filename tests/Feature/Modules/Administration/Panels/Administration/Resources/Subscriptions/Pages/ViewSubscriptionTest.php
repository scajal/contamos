<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Pages\ViewSubscription;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Pages\ViewSubscription.
 */
it('renders the subscription', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    subscribeToPro($team);

    $subscription = Subscription::query()->whereMorphedTo('billable', $team)->sole();

    Livewire::test(ViewSubscription::class, ['record' => $subscription->getKey()])
        ->assertOk()
        ->assertSee($subscription->paddle_id);
});
