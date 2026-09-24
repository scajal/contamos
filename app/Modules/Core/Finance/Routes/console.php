<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Services\MonthlySummary\Commands\SendMonthlySummariesCommand;
use App\Modules\Core\Finance\Services\RecurringRule\Commands\GenerateRecurringTransactionsCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Finance (console) routes
|--------------------------------------------------------------------------
|
| These routes correspond to the finance management in the console.
|
*/

// Hourly, because a rule's time of day is local to the member who
// created it: a single daily run would fire at the wrong hour for
// every timezone but one.
Schedule::command(GenerateRecurringTransactionsCommand::class)
    ->withoutOverlapping()
    ->hourly();

// On the 1st, once the month before has closed everywhere
// the team's members might be.
Schedule::command(SendMonthlySummariesCommand::class)
    ->withoutOverlapping()
    ->monthlyOn(1, '09:00')
    ->timezone('UTC');
