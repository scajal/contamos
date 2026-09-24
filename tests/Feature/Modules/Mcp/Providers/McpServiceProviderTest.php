<?php

declare(strict_types=1);

/**
 * Feature tests for \App\Modules\Mcp\Providers\McpServiceProvider.
 */
it('limits a user to 120 MCP requests a minute', function (): void {
    fakeAuthKitJwks();
    [$user] = memberWithTeam();
    $token = mcpToken($user);

    foreach (range(1, 120) as $request) {
        mcpCall($token, 'ping')->assertOk();
    }

    mcpCall($token, 'ping')
        ->assertTooManyRequests()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('error.message', 'Rate limit exceeded: at most 120 requests per minute. Wait a minute before calling Contamos again.');
});
