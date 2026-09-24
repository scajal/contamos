<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\NotificationController.
 */
describe('unsubscribe', function (): void {
    it('turns off only that type on that team without signing in', function (): void {
        [$user, $team] = memberWithTeam();
        $team->users()->updateExistingPivot($user->getKey(), [
            'notification_preferences' => ['budget_alerts' => false, 'monthly_summary' => true],
        ]);
        $other = Team::factory()->create();
        memberOf($other, Role::Owner, $user);

        $this->get(NotificationType::MemberChanges->unsubscribeUrl($user, $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/notifications/unsubscribe')
                ->where('isMember', true)
                ->where('team', ['name' => $team->name, 'slug' => $team->slug])
                ->where('type', 'Member changes'));

        $this->assertGuest();

        expect($user->membershipOn($team)?->notification_preferences)->toBe([
            'budget_alerts' => false,
            'monthly_summary' => true,
            'member_changes' => false,
        ])
            ->and($user->membershipOn($other)?->notification_preferences)->toBeNull();
    });

    it('changes nothing more when followed twice', function (): void {
        [$user, $team] = memberWithTeam();
        $url = NotificationType::BudgetAlerts->unsubscribeUrl($user, $team);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        expect($user->membershipOn($team)?->notification_preferences)->toBe(['budget_alerts' => false]);
    });

    it('keeps working after years and a team rename', function (): void {
        [$user, $team] = memberWithTeam();
        $url = NotificationType::MonthlySummary->unsubscribeUrl($user, $team);

        $this->travel(5)->years();
        $team->update(['name' => 'Renamed household']);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('team.name', 'Renamed household'));

        expect($user->membershipOn($team)?->wantsNotification(NotificationType::MonthlySummary))->toBeFalse();
    });

    it('refuses a link whose signature does not match', function (): void {
        [$user, $team] = memberWithTeam();
        $victim = memberOf($team, Role::Admin);
        $url = NotificationType::MemberChanges->unsubscribeUrl($user, $team);

        $this->get(str_replace($user->getKey(), $victim->getKey(), $url))
            ->assertForbidden();

        expect($victim->membershipOn($team)?->notification_preferences)->toBeNull();
    });

    it('tells a user who left the team that there is nothing to turn off', function (): void {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->get(NotificationType::MemberChanges->unsubscribeUrl($user, $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/notifications/unsubscribe')
                ->where('isMember', false)
                ->where('team.name', $team->name));
    });
});
