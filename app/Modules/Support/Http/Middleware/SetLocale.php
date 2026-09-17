<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Middleware;

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

            // Set the locale cookie.
            Cookie::queue(Cookie::make('filament_language_switch_locale', $locale, 60 * 24 * 365));

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
     * then the locale cookie, then the browser's preferred
     * language, then the application's default locale.
     */
    public function resolve(Request $request): string
    {
        // First, check if the locale is coming from
        // the route segment.
        if (is_string($locale = $request->route('locale'))) {
            return $locale;
        }

        // Then, check if the locale is coming from
        // the cookie.
        if (is_string($cookie = $request->cookie('filament_language_switch_locale'))) {
            return $cookie;
        }

        // Finally, get the locale from the browser's preferred
        // language, or the application's default locale.
        return $request->getPreferredLanguage($this->locales()) ?? config()->string('app.locale');
    }

    /**
     * Get the list of installed locale codes.
     *
     * @return array<int, string>
     */
    protected function locales(): array
    {
        return Locales::installed()->pluck('code')->toArray();
    }
}
