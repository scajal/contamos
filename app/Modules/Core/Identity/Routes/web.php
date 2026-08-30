<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

/*
|--------------------------------------------------------------------------
| Identity routes
|--------------------------------------------------------------------------
|
| These routes correspond to the identity management.
|
*/

Route::as('identity.')
    ->middleware([Authenticate::class, ValidateSessionWithWorkOS::class])
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Team routes
        |--------------------------------------------------------------------------
        |
        | These routes correspond to the team management.
        |
        */

        Route::get('teams/create', [\App\Modules\Core\Identity\Http\Controllers\User\TeamController::class, 'create'])
            ->name('teams.create');

        Route::post('teams', [\App\Modules\Core\Identity\Http\Controllers\User\TeamController::class, 'store'])
            ->name('teams.store');
    });
