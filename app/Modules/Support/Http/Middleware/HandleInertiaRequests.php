<?php

namespace App\Modules\Support\Http\Middleware;

use App\Modules\Core\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * The page components that should not be rendered
     * server-side. Marketing pages are the only ones
     * that need SSR (for SEO), so the rest opt out.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = ['teams/*', 'invitations/*', 'administration/*'];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // The resolved locale, read at render time so it
            // works for SSR requests too.
            'locale' => fn (): string => App::currentLocale(),

            // The authentication status.
            'auth' => [
                'canAccessAdministration' => ($user = $this->user($request)) && $user->canAccessPanel(Filament::getPanel('administration')),
            ],

            // The authenticated user.
            'user' => Inertia::always($user),
        ];
    }

    /**
     * Get the current authenticated user,
     * if any.
     */
    protected function user(Request $request): ?User
    {
        // If the user is not an instance of User, return null.
        if (! ($user = $request->user()) instanceof User) {
            return null;
        }

        // Load the current team.
        /** @var \App\Modules\Core\Identity\Models\User $user */
        if (isset($user->current_team_id)) {
            $user->loadMissing('currentTeam');
        }

        return $user->loadMissing('teams');
    }
}
