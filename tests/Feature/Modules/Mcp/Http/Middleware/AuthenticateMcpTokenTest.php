<?php

declare(strict_types=1);

use App\Modules\Mcp\Models\RevokedGrant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Feature tests for \App\Modules\Mcp\Http\Middleware\AuthenticateMcpToken.
 */
beforeEach(function (): void {
    fakeAuthKitJwks();
});

/**
 * Assert the response refuses the token and points the client at
 * the protected resource metadata.
 */
function assertRefusedToken(TestResponse $response): void
{
    $response->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata="'.route('mcp.oauth.protected-resource.nested', ['path' => 'mcp']).'"');
}

it('lets a valid token reach the server', function (): void {
    [$user] = memberWithTeam();

    $response = mcpCall(mcpToken($user))->assertOk();

    expect(collect($response->json('result.tools'))->pluck('name'))->toContain('list_teams');
});

it('refuses a request without a token', function (): void {
    assertRefusedToken(mcpCall(null));
});

it('refuses a malformed token', function (): void {
    assertRefusedToken(mcpCall('not-a-token'));
});

it('refuses a token issued for another audience', function (): void {
    [$user] = memberWithTeam();

    assertRefusedToken(mcpCall(mcpToken($user, ['aud' => 'https://elsewhere.test/mcp'])));
});

it('refuses a token from another issuer', function (): void {
    [$user] = memberWithTeam();

    assertRefusedToken(mcpCall(mcpToken($user, ['iss' => 'https://impostor.test'])));
});

it('refuses an expired token', function (): void {
    [$user] = memberWithTeam();

    assertRefusedToken(mcpCall(mcpToken($user, [
        'iat' => now()->subHour()->getTimestamp(),
        'exp' => now()->subMinute()->getTimestamp(),
    ])));
});

it('refuses a token without an expiry', function (): void {
    [$user] = memberWithTeam();

    assertRefusedToken(mcpCall(mcpToken($user, ['exp' => null])));
});

it('refuses a token for an unknown user', function (): void {
    [$user] = memberWithTeam();

    assertRefusedToken(mcpCall(mcpToken($user, ['sub' => 'user_unknown'])));
});

it('refuses a token whose session was revoked', function (): void {
    [$user] = memberWithTeam();
    RevokedGrant::factory()->for($user)->create(['session_id' => 'session_revoked']);

    assertRefusedToken(mcpCall(mcpToken($user, ['sid' => 'session_revoked'])));
});

it('refuses a token issued to a revoked client before it was revoked', function (): void {
    [$user] = memberWithTeam();
    $token = mcpToken($user, ['client_id' => 'client_revoked', 'iat' => now()->subMinute()->getTimestamp()]);
    RevokedGrant::factory()->for($user)->forClient('client_revoked')->create();

    assertRefusedToken(mcpCall($token));
});

it('accepts a token issued to a revoked client after it reconnected', function (): void {
    [$user] = memberWithTeam();
    RevokedGrant::factory()->for($user)->forClient('client_revoked')->create(['created_at' => now()->subHour()]);

    mcpCall(mcpToken($user, ['client_id' => 'client_revoked']))->assertOk();
});

it('ignores a token passed in the query string', function (): void {
    [$user] = memberWithTeam();

    $response = test()->withHeaders(['Accept' => 'application/json, text/event-stream'])
        ->postJson('/mcp?access_token='.mcpToken($user), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

    assertRefusedToken($response);
});

it('fetches the signing keys again when AuthKit rotated them', function (): void {
    [$user] = memberWithTeam();
    Cache::put('mcp.authkit.jwks', ['keys' => [[
        'kty' => 'RSA',
        'kid' => 'retired-key',
        'use' => 'sig',
        'alg' => 'RS256',
        'n' => authKitKeyPair()['n'],
        'e' => authKitKeyPair()['e'],
    ]]]);

    mcpCall(mcpToken($user))->assertOk();

    Http::assertSentCount(1);

    expect(Cache::get('mcp.authkit.jwks'))->toHaveKey('keys.0.kid', 'test-key');
});
