<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberLeftNotification;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Identity\Notifications\Team\MemberLeftNotification.
 */
it('names the member who left', function (string $locale, string $subject, string $sentence, string $action): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    $notification = new MemberLeftNotification($team, 'Ana Pérez');
    App::setLocale($locale);

    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe([$sentence])
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe(route('identity.teams.members.index', ['team' => $team]))
        ->and((string) $mail->render())->toContain($sentence, $action.'</a>');
})->with([
    'English' => ['en', 'Ana Pérez left Casa', 'Ana Pérez left Casa.', 'View members'],
    'Spanish' => ['es', 'Ana Pérez salió de Casa', 'Ana Pérez salió de Casa.', 'Ver miembros'],
]);
