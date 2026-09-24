<?php

declare(strict_types=1);

/**
 * Feature tests for \App\Modules\Mcp\Http\Controllers\ProtectedResourceController.
 */
describe('show', function (): void {
    it('describes the MCP server as a resource AuthKit protects', function (string $uri): void {
        $this->getJson($uri)
            ->assertOk()
            ->assertExactJson([
                'resource' => config()->string('app.url').'/mcp',
                'authorization_servers' => ['https://authkit.test'],
                'bearer_methods_supported' => ['header'],
            ]);
    })->with([
        'at the root' => '/.well-known/oauth-protected-resource',
        'for the server path' => '/.well-known/oauth-protected-resource/mcp',
    ]);
});
