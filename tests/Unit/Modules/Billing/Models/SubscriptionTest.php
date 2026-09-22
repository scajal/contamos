<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;

/**
 * Unit tests for \App\Modules\Billing\Models\Subscription.
 */
it('names the billed team as the activity log owner', function (): void {
    $team = Team::factory()->create();
    subscribeToPro($team);

    $subscription = $team->subscriptions()->sole();

    expect($subscription->activityTeamKey())->toBe($team->getKey());
});

it('leaves the activity log without a team when the billable is not a team', function (): void {
    $subscription = new Subscription;
    $subscription->billable_type = User::class;
    $subscription->billable_id = 'usr_1';

    expect($subscription->activityTeamKey())->toBeNull();
});
