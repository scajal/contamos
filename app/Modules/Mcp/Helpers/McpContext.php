<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Helpers;

use App\Modules\Core\Identity\Models\Team;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The request-wide record of an MCP call: that the work is
 * happening on behalf of an AI assistant, and which one. The
 * token middleware names the client, the server marks the call,
 * and the activity log reads both back so every entry written
 * during the call says so.
 */
class McpContext
{
    /**
     * The hidden context key that marks work done
     * through the MCP server.
     */
    protected const string VIA = 'mcp.via';

    /**
     * The hidden context key holding the connecting
     * client's identifier.
     */
    protected const string CLIENT_ID = 'mcp.client_id';

    /**
     * The hidden context key holding the session the
     * token belongs to.
     */
    protected const string SESSION_ID = 'mcp.session_id';

    /**
     * The team the current tool call is working on, which
     * Pennant resolves feature checks against.
     */
    protected static ?Team $team = null;

    /**
     * Mark the current request as an MCP call.
     */
    public static function enter(): void
    {
        Context::addHidden(self::VIA, true);
    }

    /**
     * Record the client and session the request's
     * token was issued to.
     */
    public static function identify(?string $clientId, ?string $sessionId): void
    {
        Context::addHidden(self::CLIENT_ID, $clientId);
        Context::addHidden(self::SESSION_ID, $sessionId);
    }

    /**
     * Point the current tool call at a team, or at none once
     * the call is done with it.
     */
    public static function useTeam(?Team $team): void
    {
        self::$team = $team;
    }

    /**
     * Get the team the current tool call is working on, if
     * any.
     */
    public static function team(): ?Team
    {
        return self::active() ? self::$team : null;
    }

    /**
     * Whether the current request is an MCP call.
     */
    public static function active(): bool
    {
        return Context::getHidden(self::VIA) === true;
    }

    /**
     * Get the identifier of the connecting client, if the
     * token named one.
     */
    public static function clientId(): ?string
    {
        return is_string($clientId = Context::getHidden(self::CLIENT_ID)) ? $clientId : null;
    }

    /**
     * Get a human name for the connecting client. A client that
     * registered through a metadata document is identified by
     * that document's URL, whose host names the assistant;
     * anything else is only known by its identifier.
     */
    public static function clientName(): ?string
    {
        // Without a client there is nothing to name.
        if (($clientId = self::clientId()) === null) {
            return null;
        }

        // A metadata document URL names the assistant by its host.
        if (Str::startsWith($clientId, 'https://') && is_string($host = parse_url($clientId, PHP_URL_HOST))) {
            return Str::replaceStart('www.', '', $host);
        }

        return $clientId;
    }

    /**
     * Get the properties an activity entry written during an MCP
     * call carries, or none outside one.
     *
     * @return array{via?: string, client_id?: string|null, client_name?: string|null}
     */
    public static function activityProperties(): array
    {
        if (! self::active()) {
            return [];
        }

        return [
            'via' => 'mcp',
            'client_id' => self::clientId(),
            'client_name' => self::clientName(),
        ];
    }
}
