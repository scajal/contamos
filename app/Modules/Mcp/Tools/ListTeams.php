<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists the teams the user belongs to, so the assistant can ask
 * which one is meant and knows which ones MCP can reach.
 */
#[Name('list_teams')]
#[Description("List every team the user belongs to, with its slug, name, the user's role, its plan and whether MCP access is enabled for it. Pass a team's slug as `team` to every other tool.")]
#[IsReadOnly]
class ListTeams extends Tool
{
    /**
     * Get the tool's input schema. It takes no arguments.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Handle the tool call. It reads the memberships alone and
     * never touches the team the user has open on the web.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = $request->user();

        // Each membership carries the stored role; the team turns
        // it into the role the member effectively acts as.
        $teams = $user->teams()
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => [
                'slug' => $team->getRouteKey(),
                'name' => $team->name,
                'role' => $team->effectiveRole($team->membership?->role)?->value,
                'plan' => $team->plan()->value,
                'mcp_enabled' => Feature::UseMcp->enabled($team),
            ])
            ->all();

        return Response::structured(['teams' => $teams]);
    }
}
