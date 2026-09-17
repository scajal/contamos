<?php

declare(strict_types=1);

use App\Modules\Support\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

/**
 * Unit tests for \App\Modules\Support\Http\Middleware\SetLocale.
 */
it('sets the app locale from the locale cookie on a non-marketing request', function (): void {
    $request = Request::create('/dashboard');
    $request->cookies->set('filament_language_switch_locale', 'es');

    (new SetLocale)->handle($request, fn (Request $request): \Illuminate\Http\Response => response(''));

    expect(App::currentLocale())->toBe('es');
});

it('falls back to the default locale when the cookie value is unsupported', function (): void {
    $request = Request::create('/dashboard');
    $request->cookies->set('filament_language_switch_locale', 'fr');

    (new SetLocale)->handle($request, fn (Request $request): \Illuminate\Http\Response => response(''));

    expect(App::currentLocale())->toBe(config()->string('app.locale'));
});

it('prefers the route locale segment over the cookie and queues the cookie', function (): void {
    $request = Request::create('/es');
    $request->cookies->set('filament_language_switch_locale', 'en');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => tap(new \Illuminate\Routing\Route('GET', '{locale}', []), function (\Illuminate\Routing\Route $route) use ($request): void {
        $route->bind($request);
        $route->setParameter('locale', 'es');
    }));

    (new SetLocale)->handle($request, fn (Request $request): \Illuminate\Http\Response => response(''));

    $cookie = Cookie::queued('filament_language_switch_locale');

    expect(App::currentLocale())->toBe('es')
        ->and($cookie)->toBeInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class)
        ->and($cookie->getValue())->toBe('es');
});
