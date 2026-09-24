<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Models\Team\BudgetAlert;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Reports\Team\Concerns\ReportsBudgetProgress;
use Carbon\CarbonImmutable;

/**
 * Re-arms a budget's alerts for the current month once its
 * amount is raised, so a threshold the month no longer meets
 * alerts again when it is crossed anew. Editing or deleting
 * transactions never re-arms anything.
 *
 * @see \App\Modules\Core\Finance\Actions\Team\EvaluateBudgetAlerts
 */
trait RearmsBudgetAlerts
{
    use ReportsBudgetProgress;

    /**
     * Drop the current month's alerts of the period whose
     * threshold its figures no longer meet, when its amount
     * went up from the given one.
     *
     * @param  numeric-string  $previousAmount
     */
    protected function rearmBudgetAlerts(BudgetPeriod $period, string $previousAmount): void
    {
        $month = CarbonImmutable::now()->startOfMonth();

        // Only a raised amount re-arms, and only the month the
        // period is running in has alerts to re-arm.
        if (bccomp((string) $period->amount, $previousAmount, self::BUDGET_SCALE) <= 0 || ! $period->covers($month)) {
            return;
        }

        $alerts = $period->alerts()
            ->whereDate('month', $month->toDateString())
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        // Read the month's figures against the new amount, the
        // way the Budgets page reads them.
        $activity = $this->budgetedTotalsByKey($period->team, $month)
            ->get($this->budgetKeyFor($period), ['expenses' => '0', 'income' => '0']);

        $progress = $this->budgetProgress($period, $activity['income'], $activity['expenses']);

        // Keep the alerts whose threshold is still met.
        $period->alerts()
            ->whereKey($alerts->reject(fn (BudgetAlert $alert): bool => $alert->kind->isMetBy($progress))->modelKeys())
            ->delete();
    }
}
