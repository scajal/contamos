<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feature tests for \App\Modules\Core\Identity\Http\Controllers\Team\InvitationController.
 */

/**
 * Valid payload for storing a \App\Modules\Core\Identity\Models\Team\TeamInvitation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function invitationPayload(array $overrides = []): array
{
    return [
        'email' => 'ada@example.com',
        'role' => Role::Admin->value,
        ...$overrides,
    ];
}

describe('index', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->get(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->assertRedirectToRoute('login');
    });

    it('forbids users who are not members of the team', function (): void {
        actingMember();
        $other = Team::factory()->create();

        $this->get(route('identity.teams.invitations.index', ['team' => $other->getRouteKey()]))
            ->assertForbidden();
    });

    it('renders a deferred list of invitations with their inviter', function (): void {
        [$user, $team] = actingMember();
        TeamInvitation::factory()->for($team)->for($user, 'inviter')->create(['email' => 'ada@example.com']);

        $this->get(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/invitations/index')
                ->has('user')
                ->has('filters')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('invitations.data', 1)
                    ->where('invitations.data.0.email', 'ada@example.com')
                    ->where('invitations.data.0.inviter.name', $user->name)));
    });

    it('filters invitations by status and search', function (): void {
        [$user, $team] = actingMember();
        $pending = TeamInvitation::factory()->for($team)->create(['email' => 'ada@example.com']);
        TeamInvitation::factory()->for($team)->accepted()->create(['email' => 'ada.accepted@example.com']);
        TeamInvitation::factory()->for($team)->expired()->create(['email' => 'ada.expired@example.com']);
        TeamInvitation::factory()->for($team)->create(['email' => 'grace@example.com']);

        $this->get(route('identity.teams.invitations.index', [
            'team' => $team->getRouteKey(),
            'filter' => [
                'search' => 'ada',
                'status' => 'pending',
            ],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->where('filters.search', 'ada')
                ->where('filters.status', 'pending')
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('invitations.data', 1)
                    ->where('invitations.data.0.id', $pending->getKey())));
    });

    it('filters accepted and expired invitations', function (string $status, string $email): void {
        [$user, $team] = actingMember();
        TeamInvitation::factory()->for($team)->create(['email' => 'pending@example.com']);
        TeamInvitation::factory()->for($team)->accepted()->create(['email' => 'accepted@example.com']);
        TeamInvitation::factory()->for($team)->expired()->create(['email' => 'expired@example.com']);

        $this->get(route('identity.teams.invitations.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['status' => $status],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->loadDeferredProps(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                    ->has('invitations.data', 1)
                    ->where('invitations.data.0.email', $email)));
    })->with([
        'accepted' => ['accepted', 'accepted@example.com'],
        'expired' => ['expired', 'expired@example.com'],
    ]);

    it('rejects an invalid status filter', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('identity.teams.invitations.index', [
            'team' => $team->getRouteKey(),
            'filter' => ['status' => 'unknown'],
        ]))
            ->assertRedirect()
            ->assertInvalid(['filter.status']);
    });
});

describe('create', function (): void {
    it('renders the invite member modal', function (): void {
        [$user, $team] = actingMember();

        $this->withHeaders(inertiaModalHeaders())
            ->get(route('identity.teams.invitations.create', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
                ->component('identity/teams/invitations/create'));
    });
});

describe('store', function (): void {
    it('redirects guests to login', function (): void {
        $team = Team::factory()->create();

        $this->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload())
            ->assertRedirectToRoute('login');
    });

    it('lets a free team send invitations beyond its member limit', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        TeamInvitation::factory()->count(5)->for($team)->create();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload())
            ->assertRedirect(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]));

        expect($team->invitations()->count())->toBe(6);
    });

    it('creates an invitation and emails it to the invitee', function (): void {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00'));
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'email' => ' Ada@Example.com ',
            ]))
            ->assertRedirect(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]));

        $invitation = TeamInvitation::query()->sole();

        expect($invitation->email)->toBe('ada@example.com')
            ->and($invitation->code)->toHaveLength(64)
            ->and($invitation->inviter->is($user))->toBeTrue()
            ->and($invitation->team->is($team))->toBeTrue()
            ->and($invitation->expires_at?->toDateTimeString())->toBe('2026-09-13 12:00:00');

        assertFlashedToast(trans('Invitation sent'));
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            fn (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ada@example.com'
                && $notification->invitation->is($invitation),
        );
    });

    it('rejects a missing email', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), [])
            ->assertRedirect()
            ->assertInvalid([
                'email' => trans('validation.required', ['attribute' => trans('validation.attributes.email')]),
            ]);
    });

    it('rejects an invalid email', function (): void {
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'email' => 'not-an-email',
            ]))
            ->assertRedirect()
            ->assertInvalid([
                'email' => trans('validation.email', ['attribute' => trans('validation.attributes.email')]),
            ]);
    });

    it('rejects a non-string email without running the team checks', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'email' => ['ada@example.com'],
            ]))
            ->assertRedirect()
            ->assertInvalid([
                'email' => trans('validation.string', ['attribute' => trans('validation.attributes.email')]),
            ]);

        Notification::assertNothingSent();
    });

    it('rejects the email of an existing member', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'email' => $user->email,
            ]))
            ->assertRedirect()
            ->assertInvalid([
                'email' => trans('This email already belongs to a member of the team.'),
            ]);

        Notification::assertNothingSent();
    });

    it('rejects an email that already has an open invitation', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        TeamInvitation::factory()->for($team)->create(['email' => 'ada@example.com']);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload())
            ->assertRedirect()
            ->assertInvalid([
                'email' => trans('This email already has an open invitation.'),
            ]);

        expect(TeamInvitation::query()->count())->toBe(1);
        Notification::assertNothingSent();
    });

    it('rejects an open invitation for the same email in a different case', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        TeamInvitation::factory()->for($team)->create(['email' => 'ada@example.com']);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'email' => 'Ada@Example.com',
            ]))
            ->assertRedirect()
            ->assertInvalid([
                'email' => trans('This email already has an open invitation.'),
            ]);

        expect(TeamInvitation::query()->count())->toBe(1);
        Notification::assertNothingSent();
    });

    it('allows inviting an email again once its invitation expired or was accepted', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        TeamInvitation::factory()->for($team)->expired()->create(['email' => 'ada@example.com']);
        TeamInvitation::factory()->for($team)->accepted()->create(['email' => 'ada@example.com']);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload())
            ->assertRedirect()
            ->assertValid();

        expect(TeamInvitation::query()->where('email', 'ada@example.com')->count())->toBe(3);
    });

    it('allows inviting the same email to another team', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        TeamInvitation::factory()->create(['email' => 'ada@example.com']);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload())
            ->assertRedirect()
            ->assertValid();

        expect($team->invitations()->count())->toBe(1);
    });
});

describe('resend', function (): void {
    it('renews the invitation and emails it again', function (): void {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00'));
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->for($team)->expired()->create(['code' => str_repeat('a', 64)]);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.invitations.resend', [
                'team' => $team->getRouteKey(),
                'invitation' => $invitation,
            ]))
            ->assertRedirect(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]));

        $invitation->refresh();

        expect($invitation->code)->not->toBe(str_repeat('a', 64))
            ->and($invitation->code)->toHaveLength(64)
            ->and($invitation->expires_at?->toDateTimeString())->toBe('2026-09-13 12:00:00');

        assertFlashedToast(trans('Invitation sent'));
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            fn (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $invitation->email,
        );
    });

    it('refuses to resend an accepted invitation', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->for($team)->accepted()->create();

        $this->patch(route('identity.teams.invitations.resend', [
            'team' => $team->getRouteKey(),
            'invitation' => $invitation,
        ]))
            ->assertConflict();

        Notification::assertNothingSent();
    });

    it('returns 404 for an invitation of another team', function (): void {
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->create();

        $this->patch(route('identity.teams.invitations.resend', [
            'team' => $team->getRouteKey(),
            'invitation' => $invitation,
        ]))
            ->assertNotFound();
    });
});

describe('destroy', function (): void {
    it('deletes a pending invitation', function (): void {
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->for($team)->create();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.invitations.destroy', [
                'team' => $team->getRouteKey(),
                'invitation' => $invitation,
            ]))
            ->assertRedirect(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]));

        assertFlashedToast(trans('Deleted'));
        $this->assertDatabaseMissing($invitation->getTable(), [
            $invitation->getKeyName() => $invitation->getKey(),
        ]);
    });

    it('refuses to delete an accepted invitation', function (): void {
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->for($team)->accepted()->create();

        $this->delete(route('identity.teams.invitations.destroy', [
            'team' => $team->getRouteKey(),
            'invitation' => $invitation,
        ]))
            ->assertConflict();

        $this->assertDatabaseHas($invitation->getTable(), [
            $invitation->getKeyName() => $invitation->getKey(),
        ]);
    });

    it('returns 404 for an invitation of another team', function (): void {
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->create();

        $this->delete(route('identity.teams.invitations.destroy', [
            'team' => $team->getRouteKey(),
            'invitation' => $invitation,
        ]))
            ->assertNotFound();

        $this->assertDatabaseHas($invitation->getTable(), [
            $invitation->getKeyName() => $invitation->getKey(),
        ]);
    });
});

describe('permissions', function (): void {
    it('lets every role invite people', function (Role $role): void {
        Notification::fake();
        [$user, $team] = actingMember($role);
        subscribeToPro($team);

        $this->get(route('identity.teams.invitations.create', ['team' => $team->getRouteKey()]), inertiaModalHeaders())
            ->assertOk();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload())
            ->assertRedirect()
            ->assertValid();

        expect($team->invitations()->count())->toBe(1);
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
        'member' => [Role::Member],
    ]);

    it('lets every role resend and cancel an invitation', function (Role $role): void {
        Notification::fake();
        [$user, $team] = actingMember($role);
        subscribeToPro($team);
        $invitation = TeamInvitation::factory()->for($team)->create();
        $cancelled = TeamInvitation::factory()->for($team)->create();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->patch(route('identity.teams.invitations.resend', ['team' => $team->getRouteKey(), 'invitation' => $invitation->getRouteKey()]))
            ->assertRedirect();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->delete(route('identity.teams.invitations.destroy', ['team' => $team->getRouteKey(), 'invitation' => $cancelled->getRouteKey()]))
            ->assertRedirect();

        expect($team->invitations()->count())->toBe(1);
    })->with([
        'owner' => [Role::Owner],
        'admin' => [Role::Admin],
        'member' => [Role::Member],
    ]);
});

describe('roles', function (): void {
    it('exposes the assignable roles on a pro team', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);

        $this->get(route('identity.teams.invitations.create', ['team' => $team->getRouteKey()]), inertiaModalHeaders())
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/invitations/create')
                ->where('roles', [
                    ['label' => Role::Admin->getLabel(), 'value' => Role::Admin->value],
                    ['label' => Role::Member->getLabel(), 'value' => Role::Member->value],
                ]));
    });

    it('exposes no roles on a free team', function (): void {
        [$user, $team] = actingMember();

        $this->get(route('identity.teams.invitations.create', ['team' => $team->getRouteKey()]), inertiaModalHeaders())
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/invitations/create')
                ->where('roles', []));
    });

    it('persists the invited role on a pro team', function (Role $role): void {
        Notification::fake();
        [$user, $team] = actingMember();
        subscribeToPro($team);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'role' => $role->value,
            ]))
            ->assertRedirect()
            ->assertValid();

        expect($team->invitations()->sole()->role)->toBe($role);
    })->with([
        'admin' => [Role::Admin],
        'member' => [Role::Member],
    ]);

    it('refuses to invite a member on a free team', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'role' => Role::Member->value,
            ]))
            ->assertRedirect()
            ->assertInvalid(['role']);

        expect($team->invitations()->count())->toBe(0);
    });

    it('refuses the owner role on any plan', function (): void {
        Notification::fake();
        [$user, $team] = actingMember();
        subscribeToPro($team);

        $this->from(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->post(route('identity.teams.invitations.store', ['team' => $team->getRouteKey()]), invitationPayload([
                'role' => Role::Owner->value,
            ]))
            ->assertRedirect()
            ->assertInvalid(['role']);

        expect($team->invitations()->count())->toBe(0);
    });

    it('lists the invited role', function (): void {
        [$user, $team] = actingMember();
        subscribeToPro($team);
        TeamInvitation::factory()->for($team)->as(Role::Member)->create();

        $this->get(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('identity/teams/invitations/index')
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('invitations.data.0.role', Role::Member->value)));
    });

    it('lists the role an invitee will actually get on a free team', function (): void {
        [$user, $team] = actingMember();
        $invitation = TeamInvitation::factory()->for($team)->as(Role::Member)->create();

        $this->get(route('identity.teams.invitations.index', ['team' => $team->getRouteKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $page): Assert => $page
                    ->where('invitations.data.0.role', Role::Admin->value)));

        // The stored role is untouched, so the invitation still carries
        // what it was sent as.
        expect($invitation->fresh()?->role)->toBe(Role::Member);
    });
});
