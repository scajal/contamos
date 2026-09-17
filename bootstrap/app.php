<?php

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Modules/Core/Catalog/Services/Quote/Commands',
    ])
    ->withExceptions(
        fn (Exceptions $exceptions): Exceptions => $exceptions
            ->dontTruncateRequestExceptions()
            ->shouldRenderJsonWhen(
                fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
            )
    )
    ->withMiddleware(
        fn (Middleware $middleware): Middleware => $middleware
            ->encryptCookies(except: ['appearance', 'filament_language_switch_locale'])
            ->preventRequestForgery(except: ['paddle/*'])
            ->redirectUsersTo(fn (Request $request): string => app(ResolveAuthenticationRedirect::class)->fromRequest($request))
            ->web(append: [
                \App\Modules\Support\Http\Middleware\SetLocale::class,
                \App\Modules\Support\Http\Middleware\HandleInertiaRequests::class,
                \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            ])
    )
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->create();
