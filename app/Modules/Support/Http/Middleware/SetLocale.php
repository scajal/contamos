<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Middleware;

use App\Modules\Core\Identity\Enums\User\PreferenceKey;
use App\Modules\Core\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use LaravelLang\Locales\Facades\Locales;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve the default locale from the
        // incoming request.
        $locale = $this->resolve($request);

        // If the locale is installed, set it as
        // the app's locale.
        if (in_array($locale, $this->locales(), true)) {
            App::setLocale($locale);

            // Set the locale cookie, which also rewrites a stale
            // one on a device the member did not choose it on.
            Cookie::queue(PreferenceKey::Locale->cookie($locale));

            // If there's a session instance, set the
            // locale as the session's preferred locale.
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        return $next($request);
    }

    /**
     * Resolve the locale for the given request: route segment,
     * then the signed-in member's stored locale, then the locale
     * cookie, then the browser's preferred language, then the
     * application's default locale.
     */
    public function resolve(Request $request): string
    {
        // First, check if the locale is coming from
        // the route segment.
        if (is_string($locale = $request->route('locale'))) {
            return $locale;
        }

        // Then, check if the signed-in member stored one.
        if (is_string($stored = $this->stored($request))) {
            return $stored;
        }

        // Then, check if the locale is coming from
        // the cookie.
        if (is_string($cookie = $request->cookie(PreferenceKey::Locale->cookieName()))) {
            return $cookie;
        }

        // Finally, get the locale from the browser's preferred
        // language, or the application's default locale.
        return $request->getPreferredLanguage($this->locales()) ?? config()->string('app.locale');
    }

    /**
     * Get the locale the signed-in member stored. A member who
     * never stored one adopts the locale cookie, so the choice
     * they made before signing in follows them from now on.
     */
    protected function stored(Request $request): ?string
    {
        // A visitor who is not signed in has nothing stored.
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        // A stored locale wins over whatever the cookie says.
        $stored = $user->preference(PreferenceKey::Locale);

        if ($stored !== null) {
            return $stored;
        }

        // Otherwise, a valid cookie becomes the stored locale.
        $cookie = $request->cookie(PreferenceKey::Locale->cookieName());

        if (! PreferenceKey::Locale->accepts($cookie)) {
            return null;
        }

        return $user->rememberPreference(PreferenceKey::Locale, $cookie)->value;
    }

    /**
     * Get the list of installed locale codes.
     *
     * @return array<int, string>
     */
    protected function locales(): array
    {
        /** @var array<int, string> */
        $locales = Locales::installed()->pluck('code')->toArray();

        return $locales;
    }
}
