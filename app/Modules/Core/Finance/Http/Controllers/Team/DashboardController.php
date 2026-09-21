<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display the dashboard for the team.
     */
    public function index(Team $team): Response
    {
        $since = Transaction::historyWindowStart(Feature::ViewHistory->limitOn($team->plan()))?->toDateString();

        return Inertia::render('finance/teams/dashboard', [
            'has_accounts' => $team->accounts()
                ->isActive()
                ->exists(),
            // Balances sum every transaction the team ever recorded,
            // whatever its plan hides, so they stay correct.
            'balances' => Inertia::defer(
                fn (): Collection => $team->accounts()
                    ->isActive()
                    ->balancesByCurrency()
                    ->get(),
                'balances',
            ),
            'recent_transactions' => Inertia::defer(
                fn (): Collection => $team->transactions()
                    ->with('account', 'currency')
                    ->whereRelation('account', (new Account)->qualifyColumn('is_active'), true)
                    // No listing shows what the transactions list
                    // hides, so recent activity stops at the same
                    // window start.
                    ->when(
                        $since,
                        fn (Builder $transactions, string $date): Builder => $transactions->occurredFrom($date),
                    )
                    ->latest('occurred_at')
                    ->limit(8)
                    ->get(),
                'activity',
            ),
            'team' => $team,
        ]);
    }
}
