<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Tags;

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\ResolvesTagReferences;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists the team's tags, filtered the way the Tags page
 * filters them.
 */
#[Name('list_tags')]
#[Description("List the team's tags by name, with their id, whether they are active and, on plans with budgets, the monthly budget each runs on this month per currency. Filter by `search` (part of the name) and `is_active`.")]
#[IsReadOnly]
class ListTags extends Tool
{
    use InteractsWithTeams;
    use PresentsRecords;
    use ResolvesTagReferences;

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
                ->description('Only tags whose name contains this text, ignoring case.'),
            'is_active' => $schema->boolean()
                ->description('Only active tags (true) or only inactive ones (false). Leave out for both.'),
            ...$this->cursorSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            // Validate the filters the way the Tags page does.
            $filters = $request->validate([
                'search' => ['sometimes', 'nullable', 'string', 'max:100'],
                'is_active' => ['sometimes', 'nullable', 'boolean'],
            ]);

            $search = is_string($filters['search'] ?? null) ? $filters['search'] : '';
            $isActive = $filters['is_active'] ?? null;

            // Filter, then page the tags by name.
            $query = $team->tags()
                ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.Str::lower($search).'%']))
                ->when($isActive !== null, fn ($query) => $query->isActive((bool) $isActive))
                ->orderBy('name')
                ->orderBy(new Tag()->getKeyName());

            return Response::structured($this->page(
                $query,
                $request->get('cursor'),
                fn (Tag $tag): array => $this->presentTag($team, $tag),
            ));
        });
    }
}
