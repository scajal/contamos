<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Http\Requests\Team\Monthly\IndexRequest;
use App\Modules\Core\Finance\Reports\Team\MonthlyReport;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyController
{
    /**
     * Display the monthly report for the team.
     */
    public function index(IndexRequest $request, Team $team, MonthlyReport $report): Response|RedirectResponse
    {
        $latest = $report->latestMonth();
        $selected = $request->selectedMonth();

        // A month that has not happened yet has nothing to
        // report, so the URL is cleaned back to the current
        // month rather than shown empty.
        if ($selected instanceof CarbonImmutable && $selected->greaterThan($latest)) {
            return to_route('finance.teams.monthly.index', ['team' => $team]);
        }

        $month = $selected ?? $latest;
        $earliest = $report->earliestMonth($team, $latest);

        // A month before the plan's window stays reachable but
        // carries no figures, so downgrading hides history
        // without destroying it.
        $isLocked = $report->isLocked($month, $earliest);

        return Inertia::render('finance/teams/monthly', [
            // A plan without budgets still learns they exist, so
            // the tag table carries a single upsell instead of
            // budget blocks.
            'can_budget' => Feature::ManageBudgets->enabled($team),
            'earliest_month' => $earliest->format(MonthlyReport::MONTH_FORMAT),
            'figures' => $isLocked ? [] : Inertia::defer(
                fn (): array => $report->figures($team, $month, $earliest),
                'figures',
            ),
            'has_accounts' => $team->accounts()
                ->isActive()
                ->exists(),
            'is_locked' => $isLocked,
            'latest_month' => $latest->format(MonthlyReport::MONTH_FORMAT),
            'month' => $month->format(MonthlyReport::MONTH_FORMAT),
            'team' => $team,
        ]);
    }
}
