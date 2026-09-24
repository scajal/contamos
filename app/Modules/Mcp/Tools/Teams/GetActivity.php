<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Teams;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsTeamActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Shows what one thing somebody did changed, the way the
 * activity page's sheet does: every entry the same action
 * wrote, each with its fields before and after.
 */
#[Name('get_activity')]
#[Description("Get one entry of the team's activity log by its id (from `list_activity`), together with every other entry the same action wrote (same `batch_uuid`), oldest first. Each entry lists its changed fields with the value before (`old`) and after (`new`); references such as accounts, currencies, tags and roles also carry readable `old_label` / `new_label`. Requires the permission to view activity and a Pro plan.")]
#[IsReadOnly]
class GetActivity extends Tool
{
    use NormalizesInputs;
    use PresentsTeamActivity;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'activity' => $schema->integer()
                ->description('The id of the activity entry, as listed by `list_activity`.')
                ->required(),
            ...$this->timezoneSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request): ResponseFactory {
            $this->ensureActivityAccess($user, $team);

            $timezone = $this->timezone($request->get('timezone'));

            // Only an entry of this team can be opened.
            $id = $request->get('activity');

            $activity = is_int($id) || (is_string($id) && ctype_digit($id))
                ? $team->activities()->find((int) $id)
                : null;

            if (! $activity instanceof Activity) {
                throw new ToolError('Activity entry not found.');
            }

            // Every row the same action wrote, as the sheet shows.
            $rows = $this->batch($team, $activity);

            $members = $this->members($team);

            $names = $this->references($rows);

            return Response::structured([
                'entries' => $rows
                    ->map(fn (Activity $row): array => [
                        ...$this->activityEntry($members, $row, $names, $timezone),
                        'changes' => $this->changes($row, $names),
                    ])
                    ->values()
                    ->all(),
            ]);
        });
    }
}
