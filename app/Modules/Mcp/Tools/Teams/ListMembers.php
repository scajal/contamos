<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Teams;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists the members of a team with the role each one acts as,
 * the way the members page does.
 */
#[Name('list_members')]
#[Description("List the members of the team: id, name, email, effective role (what the member can do under the team's current plan) and when they joined. Optionally search by part of a name or email.")]
#[IsReadOnly]
class ListMembers extends Tool
{
    use InteractsWithTeams;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'search' => $schema->string()
                ->description('Only members whose name or email contains this text.'),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            $request->validate([
                'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            ]);

            $search = Str::trim($request->string('search')->value());

            // The same search the members page offers: part of
            // a name or of an email.
            $query = $team->users();

            if ($search !== '') {
                $query->where(fn (Builder $query): Builder => $query
                    ->whereLike(new User()->qualifyColumn('name'), "%{$search}%")
                    ->orWhereLike(new User()->qualifyColumn('email'), "%{$search}%"));
            }

            $members = $query
                ->orderBy('name')
                ->get()
                ->map(fn (User $member): array => [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $team->effectiveRole($member->membership?->role)?->value,
                    'joined_at' => $member->membership?->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            return Response::structured(['members' => $members]);
        });
    }
}
