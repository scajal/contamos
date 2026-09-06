<?php

namespace App\Modules\Support\Http\Middleware;

use App\Modules\Core\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
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

            'auth' => [
                'canAccessAdministration' => ($user = $this->user($request)) ? $user->canAccessPanel(Filament::getPanel('administration')) : false,
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
        /** @var \App\Modules\Core\Identity\Models\User|null $user */
        $user = $request->user();

        // If the user is an instance of the User model
        // and has a current team, load the current team.
        if ($user instanceof User && isset($user->current_team_id)) {
            $user->loadMissing('currentTeam');
        }

        return $user;
    }
}
