<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Unit tests for \App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification.
 */
it('is queued and delivered by mail', function (): void {
    $notification = new TeamInvitationNotification(TeamInvitation::factory()->create());

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->via(new AnonymousNotifiable))->toBe(['mail']);
});

it('builds a mail message with the accept link', function (): void {
    $invitation = TeamInvitation::factory()->create([
        'expires_at' => CarbonImmutable::parse('2026-09-13 12:00:00'),
    ]);

    $mail = (new TeamInvitationNotification($invitation))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe(trans('You have been invited to join :team', ['team' => $invitation->team->name]))
        ->and($mail->introLines)->toBe([
            trans(':inviter has invited you to join the team :team.', [
                'inviter' => $invitation->inviter->name,
                'team' => $invitation->team->name,
            ]),
        ])
        ->and($mail->actionText)->toBe(trans('Accept invitation'))
        ->and($mail->actionUrl)->toBe(route('identity.invitations.show', ['invitation' => $invitation]))
        ->and($mail->outroLines)->toBe([
            trans('This invitation expires on :date.', ['date' => 'Sep 13, 2026']),
        ]);
});
