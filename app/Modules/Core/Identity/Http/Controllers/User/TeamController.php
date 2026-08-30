<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Http\Controllers\User;

use Inertia\Inertia;
use Inertia\Response;

class TeamController
{
    /**
     * Show a form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('identity/users/teams/create');
    }
}
