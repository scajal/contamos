<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use Inertia\Inertia;
use Inertia\Response;

class AccountController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('finance/teams/accounts/index');
    }
}
