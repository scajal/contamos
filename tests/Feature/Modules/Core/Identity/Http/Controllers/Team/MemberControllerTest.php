<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberLeftNotification;
use App\Modules\Core\Identity\Notifications\Team\MemberRemovedNotification;
use App\Modules\Core\Identity\Notifications\Team\MemberRoleUpdatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\Team\MemberController.
 */
describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('identity.teams.members.index', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders a deferred list of members with their membership', function (): void {
        [$user, $team] = actingMember();
        $user->update(['name' => 'Ada Lovelace']);
        $other = User::factory()->create(['name' => 'Grace Hopper']);
        memberOf($team, Role::Admin, $other);

        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/members/index')
                ->has('user')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('members.data', 2)
                    ->where('members.data.0.name', 'Ada Lovelace')
                    ->where('members.data.0.membership.team_id', $team->getKey())
                    ->where('members.data.1.name', 'Grace Hopper')));
    });

    it('filters members by search', function (): void {
        [$user, $team] = actingMember();
        $user->update(['name' => 'Ada Lovelace']);
        $other = User::factory()->create(['name' => 'Grace Hopper']);
        memberOf($team, Role::Admin, $other);

        $this->get(route('identity.teams.members.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['search' => 'Grace'],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.search', 'Grace')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('members.data', 1)
                    ->where('members.data.0.id', $other->getKey())));
    });

    it('rejects a search longer than 100 characters', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('identity.teams.members.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['search' => str_repeat('a', 101)],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.search']);
    });
});

describe('destroy', function (): void {
    it('redirects guests to login', function (): void {
        [$user, $team] = memberWithTeam();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        [$other, $team] = memberWithTeam();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertForbidden();
    });

    it('does not find a user who is not a member of the team', function (): void {
        [$user, $team] = actingMember();
        memberOf($team);
        $stranger = User::factory()->create();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $stranger->getKey()]))
            ->assertNotFound();
    });

    it('removes another member of the team', function (): void {
        [$user, $team] = actingMember();
        $other = User::factory()->create();
        memberOf($team, Role::Admin, $other);
        $other->switchTeam($team);

        $this->from(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertRedirectToRoute('identity.teams.members.index', ['team' => $team->getRouteKey()]);

        expect($other->fresh()?->belongsToTeam($team))->toBeFalse()
            ->and($other->fresh()?->current_team_id)->toBeNull()
            ->and($user->fresh()?->belongsToTeam($team))->toBeTrue();

        assertFlashedToast(trans('Member removed'));
    });

    it('conflicts when the member is the last one of the team', function (): void {
        [$user, $team] = actingMember();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertConflict();

        expect($user->fresh()?->belongsToTeam($team))->toBeTrue();
    });

    it('leaves the team and lands on another team of the member', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        $other = Team::factory()->create();
        $user->teams()->attach($other, ['role' => Role::Owner]);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $other->getRouteKey()]);

        expect($user->fresh()?->belongsToTeam($team))->toBeFalse()
            ->and($user->fresh()?->current_team_id)->toBe($other->getKey());

        assertFlashedToast(trans('You left the team'));
    });

    it('leaves the last team of the member and lands on the team creation page', function (): void {
        [$user, $team] = actingMember(Role::Admin);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirectToRoute('identity.teams.create');

        expect($user->fresh()?->current_team_id)->toBeNull();
    });

    it('records a removed member with the remover as causer', function (): void {
        [$user, $team] = actingMember();
        $other = User::factory()->create();
        memberOf($team, Role::Admin, $other);
        $other->switchTeam($team);
        clearActivities();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('member_removed')
            ->and($activity->causer?->is($user))->toBeTrue()
            ->and($activity->subject?->is($other))->toBeTrue()
            ->and($activity->attribute_changes['old'])->toBe(['role' => Role::Admin->value]);
    });

    it('records a member who leaves as its own causer', function (): void {
        [$owner, $team] = memberWithTeam();
        $member = memberOf($team, Role::Admin);
        $member->switchTeam($team);
        $this->actingAs($member->refresh());
        clearActivities();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]))
            ->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('member_removed')
            ->and($activity->causer?->is($member))->toBeTrue()
            ->and($activity->subject?->is($member))->toBeTrue();
    });
});

describe('destroy permissions', function (): void {
    it('lets the owner and an admin remove another member', function (Role $role): void {
        [$user, $team] = actingMember($role);
        subscribeToPro($team);
        $other = memberOf($team, Role::Member);

        $this->from(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertRedirect();

        expect($other->fresh()?->belongsToTeam($team))->toBeFalse();
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
    ]);

    it('forbids a member from removing another member', function (): void {
        [$user, $team] = actingMember(Role::Member);
        subscribeToPro($team);
        $other = memberOf($team, Role::Admin);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $other->getKey()]))
            ->assertForbidden();

        expect($other->fresh()?->belongsToTeam($team))->toBeTrue();
    });

    it('forbids removing the owner of the team', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        $owner = $team->owner;

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $owner?->getKey()]))
            ->assertForbidden();

        expect($owner?->fresh()?->belongsToTeam($team))->toBeTrue();
    });

    it('forbids the owner from leaving the team', function (): void {
        [$user, $team] = actingMember();
        memberOf($team, Role::Admin);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertForbidden();

        expect($user->fresh()?->belongsToTeam($team))->toBeTrue();
    });

    it('lets an admin and a member leave the team', function (Role $role): void {
        [$user, $team] = actingMember($role);
        subscribeToPro($team);

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirect();

        expect($user->fresh()?->belongsToTeam($team))->toBeFalse();
    })->with([
        'admin' => [Role::Admin],
        'member' => [Role::Member],
    ]);
});

describe('roles', function (): void {
    it('lists the effective role of every member on a pro team', function (): void {
        [$owner, $team] = actingMember();
        subscribeToPro($team);
        $member = memberOf($team, Role::Member);

        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/members/index')
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('members.data.0.membership.role', $owner->name < $member->name ? Role::Owner->value : Role::Member->value)
                    ->where('members.data.1.membership.role', $owner->name < $member->name ? Role::Member->value : Role::Owner->value)));
    });

    it('lists a stored member as an admin on a free team', function (): void {
        [$owner, $team] = actingMember();
        $member = memberOf($team, Role::Member);

        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('members.data', fn (Collection $members): bool => $members
                        ->pluck('membership.role')
                        ->sort()
                        ->values()
                        ->all() === [Role::Admin->value, Role::Owner->value])));

        expect($member->teams()->whereKey($team->getKey())->sole()->membership?->role)->toBe(Role::Member);
    });

    it('opens the role form for the owner and an admin on a pro team', function (Role $role): void {
        [$user, $team] = actingMember($role);
        subscribeToPro($team);
        $member = memberOf($team, Role::Member);

        $this->get(route('identity.teams.members.role.edit', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), inertiaModalHeaders())
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/members/role')
                ->where('member.id', $member->getKey())
                ->where('roles', [
                    ['label' => Role::Admin->getLabel(), 'value' => Role::Admin->value],
                    ['label' => Role::Member->getLabel(), 'value' => Role::Member->value],
                ]));
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
    ]);

    it('changes the stored role of a member', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $member = memberOf($team, Role::Admin);

        $this->from(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), [
                'role' => Role::Member->value,
            ])
            ->assertRedirectToRoute('identity.teams.members.index', ['team' => $team->getRouteKey()]);

        expect($member->roleOn($team))->toBe(Role::Member);

        assertFlashedToast(trans('Role updated'));
    });

    it('forbids a member from changing roles', function (): void {
        [$user, $team] = actingMember(Role::Member);
        subscribeToPro($team);
        $member = memberOf($team, Role::Admin);

        $this->get(route('identity.teams.members.role.edit', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), inertiaModalHeaders())
            ->assertForbidden();

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), [
            'role' => Role::Member->value,
        ])->assertForbidden();

        expect($member->roleOn($team))->toBe(Role::Admin);
    });

    it('forbids changing the role of the owner', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        subscribeToPro($team);
        $owner = $team->owner;

        $this->get(route('identity.teams.members.role.edit', ['team' => $team->getRouteKey(), 'user' => $owner?->getKey()]), inertiaModalHeaders())
            ->assertForbidden();

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $owner?->getKey()]), [
            'role' => Role::Member->value,
        ])->assertForbidden();

        expect($owner?->roleOn($team))->toBe(Role::Owner);
    });

    it('forbids changing your own role', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        subscribeToPro($team);

        $this->get(route('identity.teams.members.role.edit', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]), inertiaModalHeaders())
            ->assertForbidden();

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]), [
            'role' => Role::Member->value,
        ])->assertForbidden();

        expect($user->roleOn($team))->toBe(Role::Admin);
    });

    it('refuses the owner role and a missing role', function (mixed $role): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $member = memberOf($team, Role::Admin);

        $this->from(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), [
                'role' => $role,
            ])
            ->assertRedirect()
            ->assertInvalid(['role']);

        expect($member->roleOn($team))->toBe(Role::Admin);
    })->with([
        'owner' => [Role::Owner->value],
        'unknown' => ['editor'],
        'missing' => [null],
    ]);

    it('sends a free team to the upgrade prompt instead of the role form', function (): void {
        [$user, $team] = actingMember();
        $member = memberOf($team, Role::Admin);

        $this->get(route('identity.teams.members.role.edit', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), inertiaModalHeaders())
            ->assertRedirect(route('billing.teams.subscriptions.upgrade', [
                'team' => $team->getRouteKey(),
                'feature' => Feature::AssignRoles->value,
            ]));

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), [
            'role' => Role::Member->value,
        ])->assertRedirect(route('billing.teams.subscriptions.upgrade', [
            'team' => $team->getRouteKey(),
            'feature' => Feature::AssignRoles->value,
        ]));

        expect($member->teams()->whereKey($team->getKey())->sole()->membership?->role)->toBe(Role::Admin);
    });

    it('records a role change with the role it replaced', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $member = memberOf($team, Role::Member);
        clearActivities();

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $member->getKey()]), [
            'role' => Role::Admin->value,
        ])->assertRedirect();

        $activity = activitiesOf($team)->sole();

        expect($activity->event)->toBe('member_role_updated')
            ->and($activity->causer?->is($user))->toBeTrue()
            ->and($activity->subject?->is($member))->toBeTrue()
            ->and($activity->attribute_changes['old'])->toBe(['role' => Role::Member->value])
            ->and($activity->attribute_changes['attributes'])->toBe(['role' => Role::Admin->value]);
    });
});

describe('downgrade', function (): void {
    it('keeps a stored member working as an admin once the team leaves pro', function (): void {
        [$owner, $team] = memberWithTeam();
        subscribeToPro($team);
        $member = memberOf($team, Role::Member);
        $member->switchTeam($team);
        $this->actingAs($member);

        // The team drops back to the free plan. Pennant resolves the
        // features once per request, so the cache is flushed the way a
        // new request would.
        $team->subscriptions()->delete();
        $team->unsetRelation('subscriptions');
        Pennant::flushCache();

        // The member acts as an admin: the listing shows the role, the
        // shared permissions match, and renaming the team is allowed.
        $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('permissions', array_map(
                    fn (Permission $permission): string => $permission->value,
                    Role::Admin->permissions(),
                ))
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('members.data', fn (Collection $members): bool => $members
                        ->firstWhere('id', $member->getKey())['membership']['role'] === Role::Admin->value)));

        $this->from(route('identity.teams.settings.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.settings.update', ['team' => $team->getRouteKey()]), [
                'name' => 'Renamed',
                'slug' => 'renamed',
            ])
            ->assertRedirect();

        expect($team->fresh()?->name)->toBe('Renamed')
            // The stored role is untouched, so returning to pro
            // restores what the owner set up.
            ->and($member->teams()->whereKey($team->getKey())->sole()->membership?->role)->toBe(Role::Member);

        subscribeToPro($team);
        Pennant::flushCache();

        expect($member->roleOn($team))->toBe(Role::Member);
    });
});

describe('destroy notifications', function (): void {
    it('tells the owner and admins who removed whom, sparing the remover', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $admin = memberOf($team, Role::Admin);
        $member = memberOf($team, Role::Member);
        $removed = memberOf($team, Role::Admin);
        Notification::fake();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $removed->getKey()]))
            ->assertRedirect();

        Notification::assertSentTo(
            $admin,
            MemberRemovedNotification::class,
            fn (MemberRemovedNotification $notification): bool => $notification->team->is($team)
                && $notification->memberName === $removed->name
                && $notification->causerName === $user->name,
        );
        Notification::assertNotSentTo([$user, $member, $removed], MemberRemovedNotification::class);
    });

    it('tells the owner and admins that a member left, sparing the one who left', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        $owner = $team->owner;
        $admin = memberOf($team, Role::Admin);
        Notification::fake();

        $this->delete(route('identity.teams.members.destroy', ['team' => $team->getRouteKey(), 'user' => $user->getKey()]))
            ->assertRedirect();

        Notification::assertSentTo(
            [$owner, $admin],
            MemberLeftNotification::class,
            fn (MemberLeftNotification $notification): bool => $notification->team->is($team)
                && $notification->memberName === $user->name,
        );
        Notification::assertNotSentTo($user, MemberLeftNotification::class);
        Notification::assertNotSentTo([$owner, $admin], MemberRemovedNotification::class);
    });
});

describe('update notifications', function (): void {
    it('tells the owner and admins the old and new role, sparing the one who changed it', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        $admin = memberOf($team, Role::Admin);
        $changed = memberOf($team, Role::Admin);
        Notification::fake();

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $changed->getKey()]), [
            'role' => Role::Member->value,
        ])->assertRedirect();

        Notification::assertSentTo(
            $admin,
            MemberRoleUpdatedNotification::class,
            fn (MemberRoleUpdatedNotification $notification): bool => $notification->team->is($team)
                && $notification->memberName === $changed->name
                && $notification->causerName === $user->name
                && $notification->from === Role::Admin
                && $notification->to === Role::Member,
        );
        Notification::assertNotSentTo([$user, $changed], MemberRoleUpdatedNotification::class);
    });

    it('notifies nobody when the role stays the same', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        memberOf($team, Role::Admin);
        $changed = memberOf($team, Role::Member);
        Notification::fake();

        $this->patch(route('identity.teams.members.role.update', ['team' => $team->getRouteKey(), 'user' => $changed->getKey()]), [
            'role' => Role::Member->value,
        ])->assertRedirect();

        Notification::assertNothingSent();
    });
});
