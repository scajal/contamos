<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check() ? inertia('welcome') : to_route('login'));

/*
|--------------------------------------------------------------------------
| Authentication routes
|--------------------------------------------------------------------------
|
| This file contains the routes for the authentication module.
|
*/

require __DIR__.'/../app/Modules/Authentication/Routes/web.php';
