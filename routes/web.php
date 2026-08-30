<?php

/**
 * Fallback
 */

use Illuminate\Support\Facades\Route;

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
| Catalog routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the catalog module.
|
*/

require __DIR__.'/../app/Modules/Core/Catalog/Routes/web.php';

/*
|--------------------------------------------------------------------------
| Finance routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the finance module.
|
*/

require __DIR__.'/../app/Modules/Core/Finance/Routes/web.php';
