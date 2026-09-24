<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\Team\NotificationController.
 */
describe('edit', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $team = Team::factory()->create();

        $this->get(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->assertForbidden();
    });

    it('forbids a member of a pro team', function (): void {
        [$user, $team] = actingMember(Role::Member);
        subscribeToPro($team);

        $this->get(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->assertForbidden();
    });

    it('shows the owner and an admin every switch on by default', function (Role $role): void {
        [$user, $team] = actingMember($role);

        $this->get(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/notifications/edit')
                ->where('team.slug', $team->slug)
                ->where('notifications', [
                    ['description' => 'When a budget is about to run out or goes over.', 'enabled' => true, 'label' => 'Budget alerts', 'value' => 'budget_alerts'],
                    ['description' => 'When somebody joins or leaves the team, or a role changes.', 'enabled' => true, 'label' => 'Member changes', 'value' => 'member_changes'],
                    ['description' => 'A summary of the team\'s money when a month ends.', 'enabled' => true, 'label' => 'Monthly summary', 'value' => 'monthly_summary'],
                    ['description' => 'When a recurring transaction could not be recorded.', 'enabled' => true, 'label' => 'Recurring failures', 'value' => 'recurring_failures'],
                ]));
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
    ]);

    it('lets a stored member of a free team in, since they act as an admin', function (): void {
        [$user, $team] = actingMember(Role::Member);

        $this->get(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->assertOk();
    });

    it('shows the switches the member turned off', function (): void {
        [$user, $team] = actingMember();
        $team->users()->updateExistingPivot($user->getKey(), [
            'notification_preferences' => ['member_changes' => false],
        ]);

        $this->get(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notifications.0.enabled', true)
                ->where('notifications.1.value', 'member_changes')
                ->where('notifications.1.enabled', false));
    });
});

describe('update', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->patch(route('identity.teams.settings.notifications.update', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids a member of a pro team', function (): void {
        [$user, $team] = actingMember(Role::Member);
        subscribeToPro($team);

        $this->patch(route('identity.teams.settings.notifications.update', ['team' => $team->getRouteKey()]), [
            'budget_alerts' => false,
            'member_changes' => false,
            'monthly_summary' => false,
            'recurring_failures' => false,
        ])->assertForbidden();

        expect($user->membershipOn($team)?->notification_preferences)->toBeNull();
    });

    it('saves the switches of the signed-in member on the team alone', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        $owner = $team->owner;
        $other = Team::factory()->create();
        memberOf($other, Role::Owner, $user);

        $this->from(route('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.settings.notifications.update', ['team' => $team->getRouteKey()]), [
                'budget_alerts' => true,
                'member_changes' => false,
                'monthly_summary' => '0',
                'recurring_failures' => '1',
            ])
            ->assertRedirectToRoute('identity.teams.settings.notifications.edit', ['team' => $team->getRouteKey()]);

        assertFlashedToast(trans('Saved'));

        expect($user->membershipOn($team)?->notification_preferences)->toBe([
            'budget_alerts' => true,
            'member_changes' => false,
            'monthly_summary' => false,
            'recurring_failures' => true,
        ])
            ->and($user->membershipOn($other)?->notification_preferences)->toBeNull()
            ->and($owner?->membershipOn($team)?->notification_preferences)->toBeNull();
    });

    it('requires every switch as a boolean', function (): void {
        [$user, $team] = actingMember();

        $this->patch(route('identity.teams.settings.notifications.update', ['team' => $team->getRouteKey()]), [
            'budget_alerts' => 'maybe',
        ])->assertSessionHasErrors([
            'budget_alerts' => 'The budget alerts field must be true or false.',
            'member_changes' => 'The member changes field is required.',
            'monthly_summary' => 'The monthly summary field is required.',
            'recurring_failures' => 'The recurring failures field is required.',
        ]);

        expect($user->membershipOn($team)?->notification_preferences)->toBeNull();
    });
});
