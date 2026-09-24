<?php

declare(strict_types=1);

use App\Modules\Billing\Commands\NotifyEndedPlanOverridesCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Billing (console) routes
|--------------------------------------------------------------------------
|
| These routes correspond to the billing management in the console.
|
*/

// Hourly, because a plan granted by hand expires at any time
// of day and lapses without anybody noticing, so its Owner
// hears about the drop within the hour.
Schedule::command(NotifyEndedPlanOverridesCommand::class)
    ->withoutOverlapping()
    ->hourly();
