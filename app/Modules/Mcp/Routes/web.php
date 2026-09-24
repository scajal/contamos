<?php

declare(strict_types=1);

use App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken;
use App\Modules\Mcp\Servers\ContamosServer;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP routes
|--------------------------------------------------------------------------
|
| These routes serve the remote MCP server AI assistants connect to, and
| the metadata that sends them to WorkOS AuthKit to sign the user in.
| AuthKit is the authorization server; Contamos only checks its tokens.
|
*/

Mcp::web('mcp', ContamosServer::class)
    ->middleware([AuthenticateMcpToken::class, ThrottleRequests::using('mcp')])
    ->name('mcp.server');

// The name is the one laravel/mcp looks for when it points a
// refused client at the metadata, so it must stay as it is.
Route::get('.well-known/oauth-protected-resource/{path?}', [\App\Modules\Mcp\Http\Controllers\ProtectedResourceController::class, 'show'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
