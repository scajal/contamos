<?php

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(
        fn (Exceptions $exceptions): Exceptions => $exceptions
            ->dontTruncateRequestExceptions()
            ->shouldRenderJsonWhen(
                fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
            )
    )
    ->withMiddleware(
        fn (Middleware $middleware): Middleware => $middleware
            ->encryptCookies()
            ->preventRequestForgery()
            ->redirectUsersTo(fn (Request $request): string => app(ResolveAuthenticationRedirect::class)->fromRequest($request))
            ->web(append: [
                \App\Modules\Support\Http\Middleware\HandleInertiaRequests::class,
                \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            ])
    )
    ->withRouting(web: __DIR__.'/../routes/web.php', health: '/up')
    ->create();
