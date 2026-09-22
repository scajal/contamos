<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetPeriodStatus.
 */
it('translates the budget period status label', function (BudgetPeriodStatus $status, string $label): void {
    App::setLocale('en');

    expect($status->getLabel())->toBe($label);
})->with([
    'paused' => [BudgetPeriodStatus::Paused, 'Paused'],
    'running' => [BudgetPeriodStatus::Running, 'Running'],
    'scheduled' => [BudgetPeriodStatus::Scheduled, 'Scheduled'],
    'ended' => [BudgetPeriodStatus::Ended, 'Ended'],
]);
