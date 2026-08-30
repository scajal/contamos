<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('welcome');
    }
}
