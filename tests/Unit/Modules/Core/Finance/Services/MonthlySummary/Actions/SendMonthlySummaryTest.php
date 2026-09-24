<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Reports\Team\MonthlyReport;
use App\Modules\Core\Finance\Services\MonthlySummary\Actions\SendMonthlySummary;
use Carbon\CarbonImmutable;

/**
 * Unit tests for \App\Modules\Core\Finance\Services\MonthlySummary\Actions\SendMonthlySummary.
 */
it('refuses to send a summary without a team', function (): void {
    (new SendMonthlySummary(new MonthlyReport))->using(CarbonImmutable::parse('2026-08-01'));
})->throws(Exception::class, 'A monthly summary cannot be sent without a team.');
