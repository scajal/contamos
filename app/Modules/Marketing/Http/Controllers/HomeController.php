<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    /**
     * Redirect the root path to the homepage of the
     * request's resolved locale.
     */
    public function redirect(): RedirectResponse
    {
        return to_route('home', ['locale' => App::currentLocale()]);
    }

    /**
     * Show the marketing homepage.
     */
    public function index(Request $request, #[CurrentUser] User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return Inertia::render('marketing/home/index', [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            'alternates' => [
                'en' => route('home', ['locale' => 'en']),
                'es' => route('home', ['locale' => 'es']),
                'default' => route('home.redirect'),
            ],
        ]);
    }
}
