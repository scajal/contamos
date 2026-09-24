<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Enums\ErrorCode;
use Symfony\Component\HttpFoundation\Response;

class McpServiceProvider extends ServiceProvider
{
    /**
     * The requests a user may send to the MCP server in a
     * minute, so a looping assistant cannot hammer it.
     */
    public const int REQUESTS_PER_MINUTE = 120;

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Limit MCP requests per user, falling back to the address
     * for requests that carry no user yet. A refused request gets
     * a JSON-RPC error the assistant can read and relay.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(self::REQUESTS_PER_MINUTE)
            ->by(is_string($key = $request->user()?->getAuthIdentifier()) ? 'user:'.$key : 'ip:'.$request->ip())
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                'jsonrpc' => '2.0',
                'id' => $this->requestId($request),
                'error' => [
                    'code' => ErrorCode::INTERNAL_ERROR->value,
                    'message' => 'Rate limit exceeded: at most '.self::REQUESTS_PER_MINUTE.' requests per minute. Wait a minute before calling Contamos again.',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS, $headers)));
    }

    /**
     * Get the JSON-RPC identifier of the refused request, so the
     * client can match the error to the call it made.
     */
    protected function requestId(Request $request): int|string|null
    {
        $id = $request->json('id');

        return is_int($id) || is_string($id) ? $id : null;
    }
}
