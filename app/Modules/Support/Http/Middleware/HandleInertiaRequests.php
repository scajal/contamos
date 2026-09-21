<?php

namespace App\Modules\Support\Http\Middleware;

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Models\Team;
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

            // The chosen colour scheme, from an unencrypted cookie the
            // client writes, so the server renders the right one on the
            // first paint instead of flashing. "system" leaves the
            // choice to the device.
            'appearance' => fn (): string => HandleAppearance::resolve($request),

            // The resolved locale, read at render time so it
            // works for SSR requests too.
            'locale' => fn (): string => App::currentLocale(),

            // The authentication status.
            'auth' => [
                'canAccessAdministration' => ($user = $this->user($request)) && $user->canAccessPanel(Filament::getPanel('administration')),
            ],

            // Which of the setup steps the current team already
            // took, so the layout can offer the rest wherever the
            // user is.
            'onboarding' => fn (): ?array => $this->onboarding($request),

            // What the user may do on their current team, so the
            // layout and the pages gate actions the same way the
            // server does.
            'permissions' => fn (): array => $this->permissions($request),

            // The authenticated user.
            'user' => Inertia::always($user),
        ];
    }

    /**
     * Get the completion of the setup steps of the current team,
     * or null when the user acts on no team.
     *
     * @return array{has_account: bool, has_member: bool, has_transaction: bool}|null
     */
    protected function onboarding(Request $request): ?array
    {
        if (! ($team = $this->team($request)) instanceof Team) {
            return null;
        }

        return [
            // An account counts once it exists, even if the team
            // later deactivated it.
            'has_account' => $team->accounts()->exists(),

            // A team is no longer alone once somebody was invited,
            // whatever became of the invitation, or once it holds
            // more than the one member who created it.
            'has_member' => $team->invitations()->exists() || $team->users()->count() > 1,

            // Any transaction of any type on any account counts,
            // including an opening balance.
            'has_transaction' => $team->transactions()->exists(),
        ];
    }

    /**
     * Get the permissions the authenticated user holds on their
     * current team, as the values of the permission enum.
     *
     * @return array<int, string>
     */
    protected function permissions(Request $request): array
    {
        if (! ($user = $this->user($request)) instanceof User) {
            return [];
        }

        if (! ($team = $this->team($request)) instanceof Team) {
            return [];
        }

        return array_map(
            fn (Permission $permission): string => $permission->value,
            $user->roleOn($team)?->permissions() ?? [],
        );
    }

    /**
     * Get the team the user acts on, if any.
     */
    protected function team(Request $request): ?Team
    {
        if (! ($user = $this->user($request)) instanceof User) {
            return null;
        }

        // Team pages carry the bound team, which the membership
        // middleware has already switched the user to. Elsewhere the
        // current team is the one the user acts on.
        $team = $request->route('team');

        if ($team instanceof Team) {
            return $team;
        }

        return isset($user->current_team_id) ? $user->currentTeam : null;
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

        // Load the current team, counting the people on it so the app
        // can tell a shared ledger from a personal one without asking
        // every page to query for it.
        /** @var \App\Modules\Core\Identity\Models\User $user */
        if (isset($user->current_team_id)) {
            $user->loadMissing('currentTeam');
            $user->currentTeam?->loadCount('users');
        }

        return $user->loadMissing('teams');
    }
}
