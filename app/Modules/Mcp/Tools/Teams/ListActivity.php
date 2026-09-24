<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Teams;

use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsTeamActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists a team's activity log, newest first, the way the
 * activity page's timeline does.
 */
#[Name('list_activity')]
#[Description('List the team\'s activity log, newest first, 50 per page: what happened (event), to what (subject type, id and name), who did it (causer kind: member, support, former or system) and, for changes made through an AI assistant, `via: "mcp"` with the client that made them. Filter by `subject` type, `causer` (a member\'s id or email, from `list_members`), `from` and `to` dates. Entries with the same `batch_uuid` were one action; call `get_activity` with an id to see what changed. Requires the permission to view activity and a Pro plan.')]
#[IsReadOnly]
class ListActivity extends Tool
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
            'subject' => $schema->string()
                ->enum(array_column(ActivitySubject::cases(), 'value'))
                ->description('Only entries about this kind of record.'),
            'causer' => $schema->string()
                ->description('Only entries made by this member, given by id or email (as listed by `list_members`).'),
            'from' => $schema->string()
                ->description('Only entries recorded on or after this date, as "YYYY-MM-DD" (UTC).'),
            'to' => $schema->string()
                ->description('Only entries recorded on or before this date, as "YYYY-MM-DD" (UTC).'),
            ...$this->timezoneSchema($schema),
            ...$this->cursorSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request): ResponseFactory {
            $this->ensureActivityAccess($user, $team);

            $request->validate([
                'subject' => ['sometimes', 'nullable', 'string', Rule::enum(ActivitySubject::class)],
                'causer' => ['sometimes', 'nullable', 'string'],
                'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
                'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            ]);

            $timezone = $this->timezone($request->get('timezone'));

            $members = $this->members($team);

            // The same filters the timeline offers.
            $page = $team->activities()
                ->with('causer', 'subject')
                ->when($request->filled('subject'), fn (Builder $query): Builder => $query->forSubjectType($request->string('subject')->value()))
                ->when($request->filled('causer'), fn (Builder $query): Builder => $query->byCauser($this->causerId($team, $request->string('causer')->value())))
                ->when($request->filled('from'), fn (Builder $query): Builder => $query->occurredFrom($request->string('from')->value()))
                ->when($request->filled('to'), fn (Builder $query): Builder => $query->occurredUntil($request->string('to')->value()))
                ->orderBy(Activity::CREATED_AT, 'desc')
                ->orderBy(new Activity()->getKeyName(), 'desc')
                ->cursorPaginate($this->perPage, ['*'], 'cursor', is_string($cursor = $request->get('cursor')) && $cursor !== '' ? $cursor : null);

            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Identity\Models\Team\Activity> $rows */
            $rows = new Activity()->newCollection(array_values($page->items()));

            // The names every reference on the page points at,
            // read once per referenced model.
            $names = $this->references($rows);

            return Response::structured([
                'items' => $rows
                    ->map(fn (Activity $activity): array => $this->activityEntry($members, $activity, $names, $timezone))
                    ->values()
                    ->all(),
                'next_cursor' => $page->nextCursor()?->encode(),
            ]);
        });
    }

    /**
     * Resolve the member a causer filter names, by email or id,
     * among the team's own members.
     */
    protected function causerId(Team $team, string $causer): string
    {
        $member = $team->users()
            ->where(fn (Builder $query): Builder => $query
                ->where(new User()->qualifyColumn('email'), Str::trim($causer))
                ->orWhere(new User()->qualifyColumn(new User()->getKeyName()), Str::trim($causer)))
            ->first();

        if (! $member instanceof User) {
            throw new ToolError("No member of this team matches \"{$causer}\". Call `list_members` to see who belongs to it.");
        }

        return (string) $member->getKey();
    }
}
