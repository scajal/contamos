<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\TeamInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\App;

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

it('renders the invitation in the Contamos layout', function (string $locale, array $lines, array $footerLinks): void {
    $invitation = TeamInvitation::factory()
        ->for(User::factory()->create(['name' => 'Ana Pérez']), 'inviter')
        ->for(Team::factory()->create(['name' => 'Casa']), 'team')
        ->create(['expires_at' => CarbonImmutable::parse('2026-09-13 12:00:00')]);
    App::setLocale($locale);

    $mail = (new TeamInvitationNotification($invitation))->toMail(new AnonymousNotifiable);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe($lines['subject'])
        ->and($html)->toContain(
            asset('images/iso-logo.png'),
            $lines['greeting'],
            $lines['inviter'],
            $lines['action'].'</a>',
            'href="'.route('identity.invitations.show', ['invitation' => $invitation]).'"',
            $lines['expiry'],
            $lines['salutation'],
        )
        ->toMatch('/class="brand-wordmark"[^>]*>contamos<\/span>/')
        ->not->toContain(...$footerLinks);
})->with([
    'English' => ['en', [
        'subject' => 'You have been invited to join Casa',
        'greeting' => 'Hello!',
        'inviter' => 'Ana Pérez has invited you to join the team Casa.',
        'action' => 'Accept invitation',
        'expiry' => 'This invitation expires on Sep 13, 2026.',
        'salutation' => 'Regards,',
    ], ['Manage notifications', 'Unsubscribe']],
    'Spanish' => ['es', [
        'subject' => 'Te invitaron a unirte a Casa',
        'greeting' => '¡Hola!',
        'inviter' => 'Ana Pérez te invitó a unirte al equipo Casa.',
        'action' => 'Aceptar invitación',
        'expiry' => 'Esta invitación vence el Sep 13, 2026.',
        'salutation' => 'Saludos,',
    ], ['Gestiona tus notificaciones', 'Darse de baja']],
]);
