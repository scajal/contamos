<?php

namespace App\Modules\Billing\Http\Controllers\Team;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController
{
    /**
     * Show the specified resource in storage.
     */
    public function show(Team $team): Response
    {
        $team->subscription();

        return Inertia::render('billing/teams/subscription/show', [
            'checkouts' => [
                'monthly' => $team->subscribe(Plan::Pro->prices()['monthly']),
                'yearly' => $team->subscribe(Plan::Pro->prices()['yearly']),
            ],
            'limits' => $team->plan()->limits(),
            'plan' => $team->plan()->value,
            'usage' => $team->planUsage(),
        ]);
    }
}
