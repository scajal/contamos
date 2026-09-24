<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Middleware;

use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Helpers\McpContext;
use App\Modules\Mcp\Models\RevokedGrant;
use Carbon\CarbonImmutable;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Authenticate an MCP request by the WorkOS AuthKit access token
 * in its Authorization header. Contamos is only the resource
 * server: AuthKit issues the token, and this checks it was issued
 * by AuthKit, for this server, is still valid, and belongs to a
 * known user whose grant was not revoked.
 *
 * It is an authenticating middleware, so the framework runs it
 * ahead of the rate limiter, which counts requests per user.
 */
class AuthenticateMcpToken implements AuthenticatesRequests
{
    /**
     * The cache key the AuthKit signing keys are kept under.
     */
    protected const string JWKS_CACHE_KEY = 'mcp.authkit.jwks';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only a bearer token in the Authorization header counts;
        // one in the query string or the body is ignored.
        if (! is_string($token = $request->bearerToken()) || $token === '') {
            return $this->unauthorized('An access token is required.');
        }

        // The token must be a valid AuthKit token for this server.
        if (($claims = $this->verify($token)) === null) {
            return $this->unauthorized('The access token is invalid or has expired.');
        }

        // The token's subject is the WorkOS user it was issued to.
        $user = User::query()
            ->where('external_id', $claims['sub'])
            ->isActive()
            ->first();

        if (! $user instanceof User) {
            return $this->unauthorized('The access token does not belong to a known user.');
        }

        // A grant the user revoked stops working at once.
        if ($this->revoked($user, $claims)) {
            return $this->unauthorized('The access token was revoked.');
        }

        // Authenticate the user for this request only, and record
        // which client and session the token belongs to.
        Auth::setUser($user);
        McpContext::identify($claims['client_id'], $claims['sid']);

        return $next($request);
    }

    /**
     * Get the canonical URL of the MCP server, the audience every
     * token must be issued for. It must match exactly what users
     * paste into their assistant.
     */
    public static function resource(): string
    {
        return Str::finish(config()->string('app.url'), '/').'mcp';
    }

    /**
     * Get the issuer every token must come from: the
     * AuthKit domain.
     */
    public static function issuer(): string
    {
        return 'https://'.config()->string('services.workos.authkit_domain');
    }

    /**
     * Verify the token's signature and claims, returning the
     * claims the server relies on, or null when the token must be
     * refused.
     *
     * @return array{sub: string, client_id: string|null, sid: string|null, iat: int}|null
     */
    protected function verify(string $token): ?array
    {
        try {
            $payload = (array) $this->decode($token);
        } catch (Throwable) {
            return null;
        }

        // The issuer must be AuthKit.
        if (($payload['iss'] ?? null) !== static::issuer()) {
            return null;
        }

        // The audience must name this server.
        $audience = is_array($payload['aud'] ?? null) ? $payload['aud'] : [$payload['aud'] ?? null];

        if (! in_array(static::resource(), $audience, true)) {
            return null;
        }

        // A token must expire and name its subject.
        if (! is_int($payload['exp'] ?? null) || ! is_string($payload['sub'] ?? null) || $payload['sub'] === '') {
            return null;
        }

        return [
            'sub' => $payload['sub'],
            'client_id' => is_string($payload['client_id'] ?? null) ? $payload['client_id'] : null,
            'sid' => is_string($payload['sid'] ?? null) ? $payload['sid'] : null,
            'iat' => is_int($payload['iat'] ?? null) ? $payload['iat'] : 0,
        ];
    }

    /**
     * Decode the token against AuthKit's signing keys, which also
     * checks its signature and expiry. The keys are cached; when a
     * token names a key the cache does not hold yet, AuthKit
     * rotated them, so they are fetched again once.
     */
    protected function decode(string $token): object
    {
        try {
            return JWT::decode($token, JWK::parseKeySet($this->keys()));
        } catch (\UnexpectedValueException $unexpectedValueException) {
            // Anything but an unknown key is final.
            if (! Str::contains($unexpectedValueException->getMessage(), '"kid"')) {
                throw $unexpectedValueException;
            }

            Cache::forget(self::JWKS_CACHE_KEY);

            return JWT::decode($token, JWK::parseKeySet($this->keys()));
        }
    }

    /**
     * Get AuthKit's JSON Web Key Set, cached for an hour.
     *
     * @return array<string, mixed>
     */
    protected function keys(): array
    {
        /** @var array<string, mixed> $keys */
        $keys = Cache::remember(self::JWKS_CACHE_KEY, CarbonImmutable::now()->addHour(), function (): array {
            /** @var array<string, mixed> $keys */
            $keys = Http::acceptJson()
                ->timeout(5)
                ->get(static::issuer().'/oauth2/jwks')
                ->throw()
                ->json();

            return $keys;
        });

        return $keys;
    }

    /**
     * Whether the user revoked the grant the token belongs to.
     *
     * @param  array{sub: string, client_id: string|null, sid: string|null, iat: int}  $claims
     */
    protected function revoked(User $user, array $claims): bool
    {
        return RevokedGrant::query()
            ->covering($user, $claims['sid'], $claims['client_id'], CarbonImmutable::createFromTimestamp($claims['iat']))
            ->exists();
    }

    /**
     * Refuse the request. The MCP route adds the WWW-Authenticate
     * header pointing the client at the protected resource
     * metadata, which starts the OAuth flow.
     */
    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => 'invalid_token',
            'error_description' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
