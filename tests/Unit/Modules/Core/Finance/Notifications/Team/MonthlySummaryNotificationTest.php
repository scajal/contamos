<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Notifications\Team\MonthlySummaryNotification;
use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Notifications\Team\MonthlySummaryNotification.
 */

/**
 * A summary of August 2026 holding a peso month that spent
 * and blew one budget, and a dollar month that only earned
 * and kept its tag budget.
 */
function augustSummary(Team $team): MonthlySummaryNotification
{
    return new MonthlySummaryNotification($team, '2026-08', [
        [
            'currency' => ['code' => 'ARS', 'decimal_places' => 2, 'symbol' => '$'],
            'income' => '1500.5',
            'expenses' => '1200.25',
            'net' => '300.25',
            'categories' => [
                ['category' => 'housing', 'expenses' => '1000'],
                ['category' => 'food', 'expenses' => '200.25'],
            ],
            'budgets' => [
                ['category' => 'food', 'tag' => null, 'amount' => '150', 'spent' => '200.25', 'remaining' => '-50.25'],
            ],
        ],
        [
            'currency' => ['code' => 'USD', 'decimal_places' => 2, 'symbol' => 'US$'],
            'income' => '80',
            'expenses' => '0',
            'net' => '80',
            'categories' => [],
            'budgets' => [
                ['category' => null, 'tag' => 'Trip | Sur', 'amount' => '50', 'spent' => '0', 'remaining' => '50'],
            ],
        ],
    ]);
}

it('is switched by the monthly summary setting', function (): void {
    expect(augustSummary(Team::factory()->create())->type())->toBe(NotificationType::MonthlySummary);
});

it('sums up the month in the recipient locale and links to its report', function (string $locale, string $subject, string $intro, array $rendered): void {
    $team = Team::factory()->create(['name' => 'Casa']);
    App::setLocale($locale);

    $mail = augustSummary($team)->toMail(User::factory()->create());

    expect($mail->subject)->toBe($subject)
        ->and($mail->introLines)->toBe([$intro])
        ->and($mail->actionUrl)->toBe(route('finance.teams.monthly.index', ['month' => '2026-08', 'team' => $team]))
        ->and((string) $mail->render())->toContain($intro, ...$rendered)
        ->not->toContain('Top categories in USD', 'Principales categorías en USD');
})->with([
    'English' => ['en', 'Your August 2026 summary for Casa', 'Here is how August 2026 went for Casa.', [
        '>Currency</th>', '$ 1,500.50', '$ 1,200.25', '$ 300.25', 'US$ 80.00',
        'Top categories in ARS', '>Housing</td>', '$ 1,000.00',
        '>Food</td>', '$ 150.00', 'Over budget',
        '>Trip | Sur</td>', 'US$ 50.00', 'Within budget',
        'View the monthly report</a>',
    ]],
    'Spanish' => ['es', 'Tu resumen de agosto 2026 de Casa', 'Así fue agosto 2026 para Casa.', [
        '>Moneda</th>', '$ 1.500,50', '$ 1.200,25', '$ 300,25', 'US$ 80,00',
        'Principales categorías en ARS', '>Vivienda</td>', '$ 1.000,00',
        '>Comida</td>', '$ 150,00', 'Por encima del presupuesto',
        '>Trip | Sur</td>', 'US$ 50,00', 'Dentro del presupuesto',
        'Ver el informe del mes</a>',
    ]],
]);

it('leaves the budget table out of a month without budgets', function (): void {
    $notification = new MonthlySummaryNotification(Team::factory()->create(), '2026-08', [[
        'currency' => ['code' => 'ARS', 'decimal_places' => 2, 'symbol' => '$'],
        'income' => '0',
        'expenses' => '10',
        'net' => '-10',
        'categories' => [['category' => 'food', 'expenses' => '10']],
        'budgets' => [],
    ]]);

    expect((string) $notification->toMail(User::factory()->create())->render())
        ->toContain('Top categories in ARS', '-$ 10.00')
        ->not->toContain('>Budget</th>');
});
