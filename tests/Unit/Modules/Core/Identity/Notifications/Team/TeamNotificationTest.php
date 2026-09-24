<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberLeftNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/**
 * Unit tests for \App\Modules\Core\Identity\Notifications\Team\TeamNotification,
 * through \App\Modules\Core\Identity\Notifications\Team\MemberLeftNotification.
 */

/**
 * Get the only email the array mailer holds.
 */
function sentTeamEmail(): Email
{
    /** @var \Illuminate\Mail\Transport\ArrayTransport $transport */
    $transport = Mail::mailer('array')->getSymfonyTransport();

    /** @var \Symfony\Component\Mailer\SentMessage $sent */
    $sent = $transport->messages()->sole();

    /** @var \Symfony\Component\Mime\Email $email */
    $email = $sent->getOriginalMessage();

    return $email;
}

it('is queued, delivered by mail and dropped once the team is gone', function (): void {
    $notification = new MemberLeftNotification(Team::factory()->create(), 'Ana Pérez');

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->via(User::factory()->make()))->toBe(['mail'])
        ->and($notification->deleteWhenMissingModels)->toBeTrue()
        ->and($notification->type())->toBe(NotificationType::MemberChanges);
});

it('links the footer to the switches and to a one-click unsubscribe', function (): void {
    [$recipient, $team] = memberWithTeam();

    $recipient->notify(new MemberLeftNotification($team, 'Ana Pérez'));

    $email = sentTeamEmail();
    $unsubscribeUrl = NotificationType::MemberChanges->unsubscribeUrl($recipient, $team);

    expect($email->getHtmlBody())->toBeString()
        ->toContain(
            'href="'.route('identity.teams.settings.notifications.edit', ['team' => $team]).'"',
            'href="'.$unsubscribeUrl.'"',
        )
        ->and($email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString())->toBe('<'.$unsubscribeUrl.'>');
});

it('renders in the locale the recipient stored, whatever the current one', function (): void {
    [$recipient, $team] = memberWithTeam();
    $team->update(['name' => 'Casa']);
    $recipient->rememberPreference(PreferenceKey::Locale, 'es');
    App::setLocale('en');

    $recipient->notify(new MemberLeftNotification($team, 'Ana Pérez'));

    $email = sentTeamEmail();

    expect($email->getSubject())->toBe('Ana Pérez salió de Casa')
        ->and($email->getHtmlBody())->toContain('Ana Pérez salió de Casa.', 'Ver miembros', 'Gestiona tus notificaciones');
});

it('renders in english for a recipient who stored no locale', function (): void {
    [$recipient, $team] = memberWithTeam();
    $team->update(['name' => 'Casa']);
    App::setLocale('es');

    $recipient->notify(new MemberLeftNotification($team, 'Ana Pérez'));

    expect(sentTeamEmail()->getSubject())->toBe('Ana Pérez left Casa');
});
