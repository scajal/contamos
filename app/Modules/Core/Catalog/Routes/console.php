<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Services\Quote\Commands\SyncScheduledQuotesCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Catalog (console) routes
|--------------------------------------------------------------------------
|
| These routes correspond to the catalog management in the console.
|
*/

Schedule::command(SyncScheduledQuotesCommand::class)
    ->timezone('America/Montevideo')
    ->withoutOverlapping()
    ->dailyAt('07:00');
