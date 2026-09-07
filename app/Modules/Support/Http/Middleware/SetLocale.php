<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * The locales supported by the application.
     *
     * @var array<int, string>
     */
    public const array LOCALES = ['en', 'es'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = static::resolve($request);

        App::setLocale($locale);

        $response = $next($request);

        // If the route carries an explicit locale segment that differs from the
        // cookie, persist it for a year so future requests without the segment
        // (such as in-app pages) keep following the visitor's choice.
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && $routeLocale !== $request->cookie('locale')) {
            $response->headers->setCookie(Cookie::make('locale', $locale, 60 * 24 * 365));
        }

        return $response;
    }

    /**
     * Resolve the locale for the given request: route segment,
     * then the locale cookie, then the browser's preferred
     * language, then the application's default locale.
     */
    public static function resolve(Request $request): string
    {
        $route = $request->route('locale');

        if (is_string($route) && in_array($route, self::LOCALES, true)) {
            return $route;
        }

        $cookie = $request->cookie('locale');

        if (is_string($cookie) && in_array($cookie, self::LOCALES, true)) {
            return $cookie;
        }

        return $request->getPreferredLanguage(self::LOCALES) ?? config()->string('app.locale');
    }
}
