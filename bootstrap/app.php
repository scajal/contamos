<?php

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Catalog\Console\Commands\SyncScheduledQuotesCommand;
use Illuminate\Console\Scheduling\Schedule;
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
    ->withCommands([
        __DIR__.'/../app/Modules/Core/Catalog/Console/Commands',
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(SyncScheduledQuotesCommand::class)
            ->weekdays()
            ->dailyAt('18:00')
            ->timezone('America/Montevideo')
            ->withoutOverlapping();
    })
    ->create();
