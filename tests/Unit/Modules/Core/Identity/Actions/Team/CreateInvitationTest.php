<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Actions\Team\CreateInvitation;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Unit tests for \App\Modules\Core\Identity\Actions\Team\CreateInvitation.
 */
it('throws when no team was provided', function (): void {
    $user = User::factory()->create();

    (new CreateInvitation)
        ->by($user)
        ->using(['email' => 'ada@example.com']);
})->throws(Exception::class, 'An invitation cannot be created without a team.');

it('throws when no inviter was provided', function (): void {
    $team = Team::factory()->create();

    (new CreateInvitation)
        ->for($team)
        ->using(['email' => 'ada@example.com']);
})->throws(Exception::class, 'An invitation cannot be created without an inviter.');

it('creates the invitation for the team and emails it to the invitee', function (): void {
    Notification::fake();
    [$user, $team] = memberWithTeam();

    $invitation = (new CreateInvitation)
        ->for($team)
        ->by($user)
        ->using(['email' => 'ada@example.com']);

    expect($invitation)->toBeInstanceOf(TeamInvitation::class)
        ->and($invitation->exists)->toBeTrue()
        ->and($invitation->email)->toBe('ada@example.com')
        ->and($invitation->team->is($team))->toBeTrue()
        ->and($invitation->inviter->is($user))->toBeTrue()
        ->and($invitation->code)->toHaveLength(64)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(
        TeamInvitationNotification::class,
        fn (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ada@example.com'
            && $notification->invitation->is($invitation),
    );
});
