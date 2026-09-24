<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Events\Team\BudgetThresholdReached;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Reports\Team\Concerns\ReportsBudgetProgress;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Exception;

/**
 * Emails the Owner and Admins when the current month reaches
 * a running budget's warning threshold or goes over its
 * amount, at most once per budget, month and threshold.
 * It runs once a write of transactions is done, whichever
 * path made it, reading the same progress the Budgets page
 * shows.
 */
class EvaluateBudgetAlerts
{
    use ReportsBudgetProgress;

    /**
     * The team whose budgets are evaluated.
     */
    public ?Team $team = null;

    /**
     * Set the team whose budgets are evaluated.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Evaluate the team's running budgets after transactions
     * dated at the given instants were written. Only the
     * current month alerts, so a write dated in another
     * month leaves the budgets alone. A movement that
     * jumps past both thresholds at once sends only the
     * alert that it went over, and the warning is then
     * held as sent too, so one crossing sends one email.
     *
     * @param  iterable<int, \Carbon\CarbonInterface|null>  $occurredAt
     */
    public function using(iterable $occurredAt): void
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('Budget alerts cannot be evaluated without a team.');
        }

        $team = $this->team;
        $month = CarbonImmutable::now()->startOfMonth();

        // A write that touched no movement of this month moves
        // none of this month's budgets.
        if (! $this->touches($occurredAt, $month)) {
            return;
        }

        // The periods in force this month are the running ones,
        // and none are when the plan does not include budgets.
        $periods = $this->budgetPeriodsFor($team, $month);

        if ($periods->isEmpty()) {
            return;
        }

        $totals = $this->budgetedTotalsByKey($team, $month);

        foreach ($periods as $period) {
            // Read the month's figures for the period the way the
            // Budgets page reads them.
            $activity = $totals->get($this->budgetKeyFor($period), ['expenses' => '0', 'income' => '0']);

            $this->alert($team, $period, $month, $this->budgetProgress($period, $activity['income'], $activity['expenses']));
        }
    }

    /**
     * Whether any of the instants falls in the given month,
     * read in the time zone the month's totals are summed in.
     *
     * @param  iterable<int, \Carbon\CarbonInterface|null>  $occurredAt
     */
    protected function touches(iterable $occurredAt, CarbonImmutable $month): bool
    {
        foreach ($occurredAt as $instant) {
            if ($instant !== null && CarbonImmutable::instance($instant)->utc()->isSameMonth($month)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send the alert the month's progress calls for, if any
     * is still owed.
     *
     * @param  array{amount: numeric-string, income: numeric-string, spent: numeric-string, remaining: numeric-string, percentage: float|null, warning_threshold: int|null}  $progress
     */
    protected function alert(Team $team, BudgetPeriod $period, CarbonImmutable $month, array $progress): void
    {
        // Going over outranks the warning, which it records as
        // sent alongside, so a later movement does not warn
        // about a budget that is already over.
        $kinds = match (true) {
            BudgetAlertKind::Over->isMetBy($progress) => [BudgetAlertKind::Over, BudgetAlertKind::Warning],
            BudgetAlertKind::Warning->isMetBy($progress) => [BudgetAlertKind::Warning],
            default => [],
        };

        // Nothing is owed below the thresholds.
        if ($kinds === []) {
            return;
        }

        // Record every threshold crossed. The unique index makes
        // sure only the write that recorded the first one sends
        // it, even when two requests race.
        $recorded = array_map(fn (BudgetAlertKind $kind): bool => $period->alerts()
            ->createOrFirst(['month' => $month, 'kind' => $kind])
            ->wasRecentlyCreated, $kinds);

        if (! $recorded[0]) {
            return;
        }

        // Email the figures as they stood when the threshold
        // was crossed.
        BudgetThresholdReached::dispatch(
            $team,
            $period,
            $kinds[0],
            $month,
            $progress['amount'],
            $progress['spent'],
            (float) $progress['percentage'],
        );
    }
}
