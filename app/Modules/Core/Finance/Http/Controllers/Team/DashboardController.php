<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Team $team): Response
    {
        return Inertia::render('finance/teams/dashboard', [
            'balances' => Inertia::defer(
                fn (): Collection => $team->accounts()
                    ->isActive()
                    ->balancesByCurrency()
                    ->get(),
            ),
        ]);
    }
}
