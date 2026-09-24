<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberJoinedNotification;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Identity\Notifications\Team\MemberJoinedNotification.
 */
it('names the person who joined and the role they act as', function (string $locale, string $subject, string $sentence, string $action): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    subscribeToPro($team);
    $notification = new MemberJoinedNotification($team, 'Ana Pérez', Role::Member);
    App::setLocale($locale);

    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe([$sentence])
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe(route('identity.teams.members.index', ['team' => $team]))
        ->and((string) $mail->render())->toContain($sentence, $action.'</a>');
})->with([
    'English' => ['en', 'Ana Pérez joined Casa', 'Ana Pérez joined Casa as Member.', 'View members'],
    'Spanish' => ['es', 'Ana Pérez se unió a Casa', 'Ana Pérez se unió a Casa como Miembro.', 'Ver miembros'],
]);
