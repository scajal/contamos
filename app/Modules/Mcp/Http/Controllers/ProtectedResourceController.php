<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Controllers;

use App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken;

/**
 * Serves the OAuth Protected Resource Metadata (RFC 9728) that
 * tells an AI assistant where to sign the user in before it
 * calls the MCP server.
 */
class ProtectedResourceController
{
    /**
     * Show the metadata document. The resource is the canonical
     * MCP URL and the only authorization server is AuthKit, since
     * assistants read the first entry alone.
     *
     * @return array{resource: string, authorization_servers: array<int, string>, bearer_methods_supported: array<int, string>}
     */
    public function show(): array
    {
        return [
            'resource' => AuthenticateMcpToken::resource(),
            'authorization_servers' => [AuthenticateMcpToken::issuer()],
            'bearer_methods_supported' => ['header'],
        ];
    }
}
