<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberJoinedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\InvitationController.
 */
describe('show', function (): void {
    it('redirects guests to login and remembers the invitation url', function (): void {
        $invitation = TeamInvitation::factory()->create();

        $this->get(route('identity.invitations.show', ['invitation' => $invitation]))
            ->assertRedirectToRoute('login')
            ->assertSessionHas('url.intended', route('identity.invitations.show', ['invitation' => $invitation]));
    });

    it('returns 404 for an unknown code', function (): void {
        $this->actingAs(User::factory()->create());

        $this->get(route('identity.invitations.show', ['invitation' => str_repeat('x', 64)]))
            ->assertNotFound();
    });

    it('renders a pending invitation sent to the current user', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'Ada@Example.com']);

        $this->get(route('identity.invitations.show', ['invitation' => $invitation]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/invitations/show')
                ->where('invitation.code', $invitation->code)
                ->where('invitation.accepted_at', null)
                ->where('invitation.team.name', $invitation->team->name)
                ->where('invitation.inviter.name', $invitation->inviter->name)
                ->where('isForCurrentUser', true)
                ->where('isTeamFull', false));
    });

    it('flags a full team so the invitee cannot accept', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);
        $invitation->team->users()->attach(User::factory()->count(2)->create(), ['role' => Role::Admin]);

        $this->get(route('identity.invitations.show', ['invitation' => $invitation]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/invitations/show')
                ->where('isTeamFull', true));
    });

    it('renders an invitation sent to another email as not for the current user', function (): void {
        $this->actingAs(User::factory()->create(['email' => 'grace@example.com']));
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);

        $this->get(route('identity.invitations.show', ['invitation' => $invitation]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('isForCurrentUser', false));
    });

    it('renders accepted and expired invitations without aborting', function (string $state): void {
        $this->actingAs(User::factory()->create(['email' => 'ada@example.com']));
        $invitation = TeamInvitation::factory()->{$state}()->create(['email' => 'ada@example.com']);

        $this->get(route('identity.invitations.show', ['invitation' => $invitation]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('invitation.code', $invitation->code)
                ->where('isForCurrentUser', true));
    })->with(['accepted', 'expired']);
});

describe('accept', function (): void {
    it('redirects guests to login', function (): void {
        $invitation = TeamInvitation::factory()->create();

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('login');
    });

    it('joins the team, marks the invitation accepted and switches to the team', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $invitation->team->getRouteKey()]);

        expect($user->fresh()?->belongsToTeam($invitation->team))->toBeTrue()
            ->and($user->fresh()?->current_team_id)->toBe($invitation->team->getKey())
            ->and($invitation->fresh()?->isAccepted())->toBeTrue();

        assertFlashedToast(trans('Welcome to :team', ['team' => $invitation->team->name]));
        assertFlashedAnalyticsEvent('join_group', ['role' => $invitation->role->value]);
    });

    it('sends the user back to the invitation when the team is full', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);
        $invitation->team->users()->attach(User::factory()->count(2)->create(), ['role' => Role::Admin]);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('identity.invitations.show', ['invitation' => $invitation]);

        expect($user->fresh()?->belongsToTeam($invitation->team))->toBeFalse()
            ->and($invitation->fresh()?->isAccepted())->toBeFalse();

        assertFlashedToast(trans('This team has reached its member limit. Ask :inviter to upgrade to Pro to make room for you.', [
            'inviter' => $invitation->inviter->name,
        ]));
    });

    it('lets a member join a full pro team', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);
        $invitation->team->users()->attach(User::factory()->count(2)->create(), ['role' => Role::Admin]);
        subscribeToPro($invitation->team);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $invitation->team->getRouteKey()]);

        expect($user->fresh()?->belongsToTeam($invitation->team))->toBeTrue();
    });

    it('keeps a single membership when the user already belongs to the team', function (): void {
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->for($team)->create(['email' => $user->email]);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]);

        expect($user->teams()->count())->toBe(1)
            ->and($invitation->fresh()?->isAccepted())->toBeTrue();
    });

    it('sends the user back to the invitation when it is not for them', function (): void {
        $user = User::factory()->create(['email' => 'grace@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('identity.invitations.show', ['invitation' => $invitation]);

        expect($user->fresh()?->belongsToTeam($invitation->team))->toBeFalse()
            ->and($invitation->fresh()?->isAccepted())->toBeFalse();
    });

    it('sends the user back to the invitation when it is no longer pending', function (string $state): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->{$state}()->create(['email' => 'ada@example.com']);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('identity.invitations.show', ['invitation' => $invitation]);

        expect($user->fresh()?->belongsToTeam($invitation->team))->toBeFalse();
    })->with(['accepted', 'expired']);

    it('lets a user who already belongs to a team accept an invitation to another', function (): void {
        [$user] = actingMember();
        $user->update(['email' => 'ada@example.com']);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com']);

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirectToRoute('finance.teams.dashboard.index', ['team' => $invitation->team->getRouteKey()]);

        expect($user->fresh()?->belongsToTeam($invitation->team))->toBeTrue()
            ->and($invitation->fresh()?->isAccepted())->toBeTrue();
    });

    it('records the member joining as one entry with the invitee as causer', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->create(['email' => 'ada@example.com', 'role' => Role::Admin]);
        clearActivities();

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirect();

        $activities = activitiesOf($invitation->team);

        expect($activities->pluck('event')->all())->toBe(['updated', 'member_added'])
            ->and($activities->pluck('batch_uuid')->unique()->filter()->all())->toHaveCount(1)
            ->and($activities->pluck('causer_id')->unique()->all())->toBe([$user->getKey()])
            ->and($activities->first()?->subject?->is($invitation))->toBeTrue()
            ->and($activities->first()?->attribute_changes['attributes'])->toHaveKey('accepted_at')
            ->and($activities->last()?->subject?->is($user))->toBeTrue()
            ->and($activities->last()?->attribute_changes['attributes'])->toBe(['role' => Role::Admin->value]);
    });
});

describe('accept notifications', function (): void {
    it('notifies the owner and admins of the team, not its members nor the one who joined', function (): void {
        $team = Team::factory()->create();
        subscribeToPro($team);
        $owner = memberOf($team, Role::Owner);
        $admin = memberOf($team, Role::Admin);
        $member = memberOf($team, Role::Member);
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        $invitation = TeamInvitation::factory()->for($team)->create(['email' => 'ada@example.com', 'role' => Role::Admin]);
        Notification::fake();

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirect();

        Notification::assertSentTo(
            [$owner, $admin],
            MemberJoinedNotification::class,
            fn (MemberJoinedNotification $notification): bool => $notification->team->is($team)
                && $notification->memberName === $user->name
                && $notification->role === Role::Admin,
        );
        Notification::assertNotSentTo([$member, $user], MemberJoinedNotification::class);
    });

    it('spares a recipient who switched member changes off on that team, but not on another', function (): void {
        $recipient = User::factory()->create();
        $quiet = Team::factory()->create();
        memberOf($quiet, Role::Owner, $recipient);
        $quiet->users()->updateExistingPivot($recipient->getKey(), [
            'notification_preferences' => ['member_changes' => false],
        ]);
        $loud = Team::factory()->create();
        memberOf($loud, Role::Owner, $recipient);
        $user = User::factory()->create(['email' => 'ada@example.com'])->refresh();
        $this->actingAs($user);
        Notification::fake();

        $this->post(route('identity.invitations.accept', [
            'invitation' => TeamInvitation::factory()->for($quiet)->create(['email' => 'ada@example.com']),
        ]))->assertRedirect();

        Notification::assertNothingSentTo($recipient);

        $this->post(route('identity.invitations.accept', [
            'invitation' => TeamInvitation::factory()->for($loud)->create(['email' => 'ada@example.com']),
        ]))->assertRedirect();

        Notification::assertSentTo(
            $recipient,
            MemberJoinedNotification::class,
            fn (MemberJoinedNotification $notification): bool => $notification->team->is($loud),
        );
    });

    it('notifies nobody when the user already belongs to the team', function (): void {
        [$user, $team] = actingMember(Role::Admin);
        $invitation = TeamInvitation::factory()->for($team)->create(['email' => $user->email]);
        Notification::fake();

        $this->post(route('identity.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirect();

        Notification::assertNothingSent();
    });
});
