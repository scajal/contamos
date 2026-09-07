<?php

/*
|--------------------------------------------------------------------------
| Marketing routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the marketing homepage.
|
*/

require __DIR__.'/../app/Modules/Marketing/Routes/web.php';

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

/*
|--------------------------------------------------------------------------
| Billing routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the billing module.
|
*/

require __DIR__.'/../app/Modules/Billing/Routes/web.php';
