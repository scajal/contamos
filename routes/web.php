<?php

use App\Modules\Billing\Features\AddAccounts;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Support\Facades\Route;

Route::get('teams/{team}/test', fn (Team $team) => dd(
    $team->plan()->limits(),
    $team->features()->active(AddAccounts::class)
));

/**
 * Fallback
 */
Route::get('/', fn () => to_route('login'));

/*
|--------------------------------------------------------------------------
| Identity routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the identity module.
|
*/

require __DIR__.'/../app/Modules/Core/Identity/Routes/web.php';

/*
|--------------------------------------------------------------------------
| Authentication routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the authentication module.
|
*/

require __DIR__.'/../app/Modules/Authentication/Routes/web.php';

/*
|--------------------------------------------------------------------------
| Finance routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the finance module.
|
*/

require __DIR__.'/../app/Modules/Core/Finance/Routes/web.php';
