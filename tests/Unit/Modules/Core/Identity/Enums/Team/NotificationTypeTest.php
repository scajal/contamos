<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

/**
 * Unit tests for \App\Modules\Core\Identity\Enums\Team\NotificationType.
 */
it('translates the label and the explanation of the switch', function (NotificationType $type, string $label, string $description): void {
    App::setLocale('es');

    expect($type->getLabel())->toBe($label)
        ->and($type->description())->toBe($description);
})->with([
    'budget alerts' => [NotificationType::BudgetAlerts, 'Alertas de presupuesto', 'Cuando un presupuesto está por agotarse o se pasa.'],
    'member changes' => [NotificationType::MemberChanges, 'Cambios de miembros', 'Cuando alguien se une o sale del equipo, o cambia un rol.'],
    'monthly summary' => [NotificationType::MonthlySummary, 'Resumen mensual', 'Un resumen del dinero del equipo cuando termina el mes.'],
    'recurring failures' => [NotificationType::RecurringFailures, 'Recurrentes fallidos', 'Cuando no se pudo registrar un movimiento recurrente.'],
]);

it('builds a signed unsubscribe link that never expires', function (): void {
    [$user, $team] = memberWithTeam();

    $url = NotificationType::RecurringFailures->unsubscribeUrl($user, $team);

    expect($url)->toStartWith(route('identity.notifications.unsubscribe', [
        'team' => $team->getKey(),
        'type' => 'recurring_failures',
        'user' => $user->getKey(),
    ]))
        ->not->toContain('expires=')
        ->and(URL::hasValidSignature(Request::create($url)))->toBeTrue();
});
