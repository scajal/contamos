<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification.
 */
it('names the category, the amount, what went and the share, linking to the budgets', function (string $locale, BudgetAlertKind $kind, string $subject, string $sentence, string $action): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    $currency = Currency::factory()->create(['decimal_places' => 2, 'symbol' => '$']);
    $budget = BudgetPeriod::factory()->forCategory(Category::Groceries)->for($team)->create([$currency->getForeignKey() => $currency->getKey()]);
    $notification = new BudgetAlertNotification($team, $budget, $kind, CarbonImmutable::parse('2026-09-01'), '1500.000000000000000000', '1282.500000000000000000', 85.5);
    App::setLocale($locale);

    $mail = $notification->toMail(User::factory()->create());

    expect($notification->type())->toBe(NotificationType::BudgetAlerts)
        ->and($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe([$sentence])
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe(route('finance.teams.budgets.index', ['team' => $team]))
        ->and((string) $mail->render())->toContain($action.'</a>');
})->with([
    'English warning' => ['en', BudgetAlertKind::Warning, 'The Groceries budget reached 85.5% on Casa', 'Groceries has used 85.5% of its budget for September 2026 on Casa: $ 1,282.50 of $ 1,500.00.', 'View budgets'],
    'English over' => ['en', BudgetAlertKind::Over, 'The Groceries budget went over on Casa', 'Groceries went over its budget for September 2026 on Casa: $ 1,282.50 of $ 1,500.00, 85.5%.', 'View budgets'],
    'Spanish warning' => ['es', BudgetAlertKind::Warning, "El presupuesto de Supermercado llegó al 85,5\u{a0}% en Casa", "Supermercado ya usó el 85,5\u{a0}% de su presupuesto de septiembre 2026 en Casa: $ 1.282,50 de $ 1.500,00.", 'Ver presupuestos'],
    'Spanish over' => ['es', BudgetAlertKind::Over, 'El presupuesto de Supermercado se pasó en Casa', "Supermercado se pasó de su presupuesto de septiembre 2026 en Casa: $ 1.282,50 de $ 1.500,00, 85,5\u{a0}%.", 'Ver presupuestos'],
]);

it('names a tag budget after the tag', function (): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    $tag = Tag::factory()->for($team)->create(['name' => 'Vacaciones']);
    $budget = BudgetPeriod::factory()->forTag($tag)->create();
    $notification = new BudgetAlertNotification($team, $budget, BudgetAlertKind::Over, CarbonImmutable::parse('2026-09-01'), '100', '120', 120.0);
    App::setLocale('en');

    $mail = $notification->toMail(User::factory()->create());

    expect($mail->subject)->toBe('The Vacaciones budget went over on Casa');
});
