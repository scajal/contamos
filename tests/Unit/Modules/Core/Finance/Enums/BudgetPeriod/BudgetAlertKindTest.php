<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind.
 */
it('tells whether a month has crossed the threshold', function (BudgetAlertKind $kind, ?float $percentage, ?int $threshold, bool $isMet): void {
    expect($kind->isMetBy(['percentage' => $percentage, 'warning_threshold' => $threshold]))->toBe($isMet);
})->with([
    'warning reached' => [BudgetAlertKind::Warning, 80.0, 80, true],
    'warning not reached' => [BudgetAlertKind::Warning, 79.99, 80, false],
    'warning without a threshold' => [BudgetAlertKind::Warning, 150.0, null, false],
    'over past the amount' => [BudgetAlertKind::Over, 100.01, null, true],
    'over at exactly the amount' => [BudgetAlertKind::Over, 100.0, null, false],
    'a pot of nothing' => [BudgetAlertKind::Over, null, 80, false],
]);

it('labels each kind in the current locale', function (string $locale, string $warning, string $over): void {
    App::setLocale($locale);

    expect(BudgetAlertKind::Warning->getLabel())->toBe($warning)
        ->and(BudgetAlertKind::Over->getLabel())->toBe($over);
})->with([
    'English' => ['en', 'Warning', 'Over budget'],
    'Spanish' => ['es', 'Aviso', 'Por encima del presupuesto'],
]);
