<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Spatie\Activitylog\Commands\CleanActivitylogCommand;

/*
|--------------------------------------------------------------------------
| Identity (console) routes
|--------------------------------------------------------------------------
|
| These routes correspond to the identity management in the console.
|
*/

Schedule::command(CleanActivitylogCommand::class)
    ->withoutOverlapping()
    ->dailyAt('03:00');
