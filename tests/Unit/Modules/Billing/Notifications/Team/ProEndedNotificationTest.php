<?php

declare(strict_types=1);

use App\Modules\Billing\Notifications\Team\ProEndedNotification;
use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/**
 * Unit tests for \App\Modules\Billing\Notifications\Team\ProEndedNotification.
 */

/**
 * Get the only email the array mailer holds.
 */
function sentProEndedEmail(): Email
{
    /** @var \Illuminate\Mail\Transport\ArrayTransport $transport */
    $transport = Mail::mailer('array')->getSymfonyTransport();

    /** @var \Symfony\Component\Mailer\SentMessage $sent */
    $sent = $transport->messages()->sole();

    /** @var \Symfony\Component\Mime\Email $email */
    $email = $sent->getOriginalMessage();

    return $email;
}

it('is queued after commit, delivered by mail and dropped once the team is gone', function (): void {
    $notification = new ProEndedNotification(Team::factory()->create());

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->via(User::factory()->make()))->toBe(['mail'])
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->deleteWhenMissingModels)->toBeTrue();
});

it('renders the consequences of the drop in the locale the owner stored', function (?string $stored, string $current, array $lines): void {
    [$owner, $team] = memberWithTeam();
    $team->update(['name' => 'Casa']);

    if (is_string($stored)) {
        $owner->rememberPreference(PreferenceKey::Locale, $stored);
    }

    App::setLocale($current);

    $owner->notify(new ProEndedNotification($team));

    $email = sentProEndedEmail();

    expect($email->getSubject())->toBe($lines['subject'])
        ->and($email->getHtmlBody())->toBeString()
        ->toContain(
            $lines['ended'],
            $lines['switches'],
            $lines['history'],
            $lines['caps'],
            $lines['action'].'</a>',
            'href="'.route('billing.teams.subscriptions.upgrade', ['team' => $team]).'"',
            $lines['outro'],
        )
        ->not->toContain('Manage notifications', 'Gestiona tus notificaciones', 'Unsubscribe', 'Darse de baja')
        ->and($email->getHeaders()->has('List-Unsubscribe'))->toBeFalse();
})->with([
    'stored Spanish' => ['es', 'en', [
        'subject' => 'Casa volvió al plan gratis',
        'ended' => 'El plan Pro de Casa terminó, así que el equipo ahora está en el plan gratis.',
        'switches' => 'Los presupuestos dejan de seguir el gasto y ya no se crean las transacciones recurrentes.',
        'history' => 'Solo se ven los últimos 6 meses del historial. Lo anterior sigue donde estaba.',
        'caps' => 'El equipo puede tener hasta 3 cuentas activas y 2 miembros. No se eliminó nada de lo que supera esos límites, pero no podrás agregar más.',
        'action' => 'Pasarme a Pro',
        'outro' => 'Si vuelves a Pro, todo vuelve a estar como antes.',
    ]],
    'no stored locale, English' => [null, 'es', [
        'subject' => 'Casa is back on the Free plan',
        'ended' => 'The Pro plan of Casa has ended, so the team is now on the Free plan.',
        'switches' => 'Budgets stop tracking spending and recurring transactions are no longer created.',
        'history' => 'Only the last 6 months of history are visible. Older entries stay where they are.',
        'caps' => 'The team can have up to 3 active accounts and 2 members. Nothing over those limits was removed, but you cannot add more.',
        'action' => 'Upgrade to Pro',
        'outro' => 'Upgrading again brings everything back as it was.',
    ]],
]);
