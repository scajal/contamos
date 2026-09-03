<?php

use Illuminate\Support\Facades\Route;

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
| Administration routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the administration module.
|
*/

require __DIR__.'/../app/Modules/Administration/Routes/web.php';

/*
|--------------------------------------------------------------------------
| Finance routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the finance module.
|
*/

require __DIR__.'/../app/Modules/Core/Finance/Routes/web.php';
