<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
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
        return Inertia::render('finance/teams/dashboard', [
            'has_accounts' => $team->accounts()
                ->isActive()
                ->exists(),
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
                    ->latest('occurred_at')
                    ->limit(8)
                    ->get(),
                'activity',
            ),
            'team' => $team,
        ]);
    }
}
