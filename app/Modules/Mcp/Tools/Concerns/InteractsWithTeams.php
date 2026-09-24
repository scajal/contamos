<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Helpers\McpContext;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Runs a team-scoped MCP tool: resolves the team the call names
 * among the user's own, refuses a team whose plan lacks MCP
 * access, and turns every refusal into a tool error the assistant
 * can relay. The user's open team on the web is never read or
 * changed.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait InteractsWithTeams
{
    /**
     * Get the schema of the `team` argument every team-scoped
     * tool takes.
     *
     * @return array{team: \Illuminate\JsonSchema\Types\Type}
     */
    protected function teamSchema(JsonSchema $schema): array
    {
        return [
            'team' => $schema->string()
                ->description('The slug of the team to work on, as listed by `list_teams`.')
                ->required(),
        ];
    }

    /**
     * Run the callback against the team the call names, as the
     * authenticated user. A refusal raised inside becomes a tool
     * error rather than a failed call.
     *
     * @param  \Closure(\App\Modules\Core\Identity\Models\Team, \App\Modules\Core\Identity\Models\User): (\Laravel\Mcp\Response|\Laravel\Mcp\ResponseFactory)  $callback
     */
    protected function forTeam(Request $request, Closure $callback): Response|ResponseFactory
    {
        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = $request->user();

        try {
            $team = $this->resolveTeam($user, $request->get('team'));

            // Point feature checks at the named team for the call.
            McpContext::useTeam($team);

            return $callback($team, $user);
        } catch (ToolError $toolError) {
            return Response::error($toolError->getMessage());
        } finally {
            McpContext::useTeam(null);
        }
    }

    /**
     * Resolve the team among the user's own. An unknown slug and
     * a team the user does not belong to read the same, so the
     * call never reveals that a team exists.
     */
    protected function resolveTeam(User $user, mixed $slug): Team
    {
        // Only the user's memberships are searched.
        $team = is_string($slug) && $slug !== ''
            ? $user->teams()->where(new Team()->qualifyColumn(new Team()->getRouteKeyName()), $slug)->first()
            : null;

        if (! $team instanceof Team) {
            throw new ToolError('Team not found. Call `list_teams` to see the slugs of the teams you belong to.');
        }

        // MCP access is a plan feature, checked team by team.
        if (! Feature::UseMcp->enabled($team)) {
            throw new ToolError("This team's plan does not include AI assistant access. Upgrade to Pro: ".$this->upgradeUrl($team, Feature::UseMcp));
        }

        return $team;
    }

    /**
     * Refuse the call unless the team's plan includes the feature,
     * the same gate the matching web route applies.
     */
    protected function ensureFeature(Team $team, Feature $feature): void
    {
        if (! $feature->enabled($team)) {
            throw new ToolError("This team's plan does not allow this ({$feature->getLabel()}). Upgrade to Pro: ".$this->upgradeUrl($team, $feature));
        }
    }

    /**
     * Refuse the call unless the user holds the permission on the
     * team, the same check the matching web route applies.
     */
    protected function ensurePermission(User $user, Team $team, Permission $permission): void
    {
        if (! $user->hasTeamPermission($team, $permission)) {
            throw new ToolError('Your role on this team does not allow this.');
        }
    }

    /**
     * Find a record of the team by its identifier, refusing with
     * a "not found" that names the kind of record otherwise.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>|\Illuminate\Database\Eloquent\Relations\Relation<TModel, *, *>  $query
     * @return TModel
     */
    protected function findOrFail(Builder|Relation $query, mixed $id, string $label): Model
    {
        $record = is_string($id) && $id !== '' ? $query->find($id) : null;

        if (! $record instanceof Model) {
            throw new ToolError(Str::ucfirst($label).' not found.');
        }

        /** @var TModel $record */
        return $record;
    }

    /**
     * Get the page that offers the team the upgrade to Pro for
     * the given feature.
     */
    protected function upgradeUrl(Team $team, Feature $feature): string
    {
        return route('billing.teams.subscriptions.upgrade', [
            'team' => $team->getRouteKey(),
            'feature' => $feature->value,
        ]);
    }

    /**
     * Keep the given keys of validated input, the way
     * \Illuminate\Support\Arr::only() does, keyed by name so
     * they can go straight to a domain Action.
     *
     * @param  array<array-key, mixed>  $validated
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function validatedOnly(array $validated, array $keys): array
    {
        $only = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $validated)) {
                $only[$key] = $validated[$key];
            }
        }

        return $only;
    }
}
