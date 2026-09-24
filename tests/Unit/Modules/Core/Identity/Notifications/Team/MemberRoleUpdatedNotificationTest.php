<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Core\Identity\Notifications\Team\MemberRoleUpdatedNotification;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Identity\Notifications\Team\MemberRoleUpdatedNotification.
 */
it('names the member, who changed the role, and the old and new role', function (string $locale, string $subject, string $sentence, string $action): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    $notification = new MemberRoleUpdatedNotification($team, 'Ana Pérez', 'Luis Gómez', Role::Admin, Role::Member);
    App::setLocale($locale);

    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe([$sentence])
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe(route('identity.teams.members.index', ['team' => $team]))
        ->and((string) $mail->render())->toContain($sentence, $action.'</a>');
})->with([
    'English' => ['en', 'Ana Pérez\'s role changed on Casa', 'Luis Gómez changed Ana Pérez\'s role on Casa from Admin to Member.', 'View members'],
    'Spanish' => ['es', 'Cambió el rol de Ana Pérez en Casa', 'Luis Gómez cambió el rol de Ana Pérez en Casa de Administrador a Miembro.', 'Ver miembros'],
]);
