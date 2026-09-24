<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Tags;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\ResolvesTagReferences;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Activates or deactivates one of the team's tags. Tags are
 * never deleted.
 */
#[Name('set_tag_active')]
#[Description('Activate (true) or deactivate (false) a tag. An inactive tag keeps its history but cannot be put on new transactions. Tags cannot be deleted.')]
#[IsDestructive]
class SetTagActive extends Tool
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
            ...$this->tagSchema($schema),
            'active' => $schema->boolean()
                ->description('Whether the tag should be active.')
                ->required(),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            $tag = $this->tagOf($team, $request->get('tag'));

            $validated = $request->validate([
                'active' => ['required', 'boolean'],
            ]);

            // Switch the tag on or off.
            if ((bool) $validated['active']) {
                $tag->activate();
            } else {
                $tag->deactivate();
            }

            return Response::structured(['tag' => $this->presentTag($team, $tag)]);
        });
    }
}
