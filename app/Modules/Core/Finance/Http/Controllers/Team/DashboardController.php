<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('finance/teams/dashboard', [
            'balances' => Inertia::defer(
                fn (): Collection => collect(),
            ),
        ]);
    }
}
