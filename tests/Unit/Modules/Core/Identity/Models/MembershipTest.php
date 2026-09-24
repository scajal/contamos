<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Enums\Team\Role;

/**
 * Unit tests for \App\Modules\Core\Identity\Models\Membership.
 */
it('reads every notification type as on until the member turns it off', function (): void {
    [$user, $team] = memberWithTeam(Role::Admin);
    $team->users()->updateExistingPivot($user->getKey(), [
        'notification_preferences' => ['budget_alerts' => false, 'monthly_summary' => true],
    ]);

    $membership = $user->membershipOn($team);

    expect($membership?->wantsNotification(NotificationType::BudgetAlerts))->toBeFalse()
        ->and($membership?->wantsNotification(NotificationType::MemberChanges))->toBeTrue()
        ->and($membership?->notificationPreferences())->toBe([
            'budget_alerts' => false,
            'member_changes' => true,
            'monthly_summary' => true,
            'recurring_failures' => true,
        ]);
});

it('reads every notification type as on for a member who never chose', function (): void {
    [$user, $team] = memberWithTeam();

    expect($user->membershipOn($team)?->notification_preferences)->toBeNull()
        ->and($user->membershipOn($team)?->notificationPreferences())->each->toBeTrue();
});
