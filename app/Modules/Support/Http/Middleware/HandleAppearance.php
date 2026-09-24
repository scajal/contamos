<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Middleware;

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * The colour schemes a visitor can choose. Anything else, including
     * a missing cookie, means the device decides.
     *
     * @var array<int, string>
     */
    public const array APPEARANCES = ['dark', 'light'];

    /**
     * Share the chosen colour scheme with the root template, so the
     * first paint already carries it and the app never flashes the
     * wrong one.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A signed-in member's stored scheme and the cookie are
        // brought in line before the scheme is resolved.
        $user = $request->user();

        if ($user instanceof User) {
            $this->synchronize($request, $user);
        }

        View::share('appearance', self::resolve($request));

        return $next($request);
    }

    /**
     * Resolve the colour scheme from the signed-in member's stored
     * choice, then from the unencrypted cookie the client writes,
     * falling back to the device's own preference.
     */
    public static function resolve(Request $request): string
    {
        $user = $request->user();

        $appearance = $user instanceof User
            ? $user->preference(PreferenceKey::Appearance) ?? $request->cookie(PreferenceKey::Appearance->cookieName())
            : $request->cookie(PreferenceKey::Appearance->cookieName());

        return is_string($appearance) && in_array($appearance, self::APPEARANCES, true)
            ? $appearance
            : 'system';
    }

    /**
     * Bring the member's stored scheme and the cookie in line. A
     * stored scheme rewrites the cookie, so a stale one on another
     * device is replaced; a member who never stored one adopts
     * the cookie's.
     */
    protected function synchronize(Request $request, User $user): void
    {
        $stored = $user->preference(PreferenceKey::Appearance);

        // A stored scheme wins and is written back to the cookie.
        if ($stored !== null) {
            Cookie::queue(PreferenceKey::Appearance->cookie($stored));

            return;
        }

        // Otherwise, a valid cookie becomes the stored scheme.
        $cookie = $request->cookie(PreferenceKey::Appearance->cookieName());

        if (PreferenceKey::Appearance->accepts($cookie)) {
            $user->rememberPreference(PreferenceKey::Appearance, $cookie);
        }
    }
}
