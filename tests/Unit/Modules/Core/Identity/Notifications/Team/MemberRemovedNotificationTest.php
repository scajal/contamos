<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberRemovedNotification;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Identity\Notifications\Team\MemberRemovedNotification.
 */
it('names the member removed and who removed them', function (string $locale, string $subject, string $sentence, string $action): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    $notification = new MemberRemovedNotification($team, 'Ana Pérez', 'Luis Gómez');
    App::setLocale($locale);

    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe([$sentence])
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe(route('identity.teams.members.index', ['team' => $team]))
        ->and((string) $mail->render())->toContain($sentence, $action.'</a>');
})->with([
    'English' => ['en', 'Ana Pérez was removed from Casa', 'Luis Gómez removed Ana Pérez from Casa.', 'View members'],
    'Spanish' => ['es', 'Ana Pérez ya no está en Casa', 'Luis Gómez quitó a Ana Pérez de Casa.', 'Ver miembros'],
]);
