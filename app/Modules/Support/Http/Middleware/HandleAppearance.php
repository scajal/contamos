<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        View::share('appearance', self::resolve($request));

        return $next($request);
    }

    /**
     * Resolve the colour scheme from the unencrypted cookie the client
     * writes, falling back to the device's own preference.
     */
    public static function resolve(Request $request): string
    {
        $appearance = $request->cookie('appearance');

        return is_string($appearance) && in_array($appearance, self::APPEARANCES, true)
            ? $appearance
            : 'system';
    }
}
