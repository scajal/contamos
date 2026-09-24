<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Tags;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\UpdateTag as UpdateTagAction;
use App\Modules\Core\Finance\Http\Requests\Team\Tag\Concerns\ValidatesTagInputs;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
 * Renames one of the team's tags and moves the budget it runs
 * on from this month on.
 */
#[Name('update_tag')]
#[Description("Rename a tag and/or change its monthly budget per currency from this month on; past months keep the amount that applied to them. A null amount stops that currency's budget. Leave out what should not change.")]
#[IsDestructive]
class UpdateTag extends Tool
{
    use InteractsWithTeams;
    use PresentsRecords;
    use ResolvesTagReferences;
    use ValidatesTagInputs;

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
            'name' => $schema->string()
                ->description("The tag's new name. Leave out to keep it."),
            ...$this->tagBudgetsSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, UpdateTagAction $updateTag): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $updateTag): ResponseFactory {
            $tag = $this->tagOf($team, $request->get('tag'));

            // A name left out keeps the current one.
            $input = ['name' => $request->get('name') ?? $tag->name];

            // Budgets are only offered on a plan that has them,
            // keyed by currency the way the web form sends them.
            if ($request->get('budgets') !== null) {
                $this->ensureFeature($team, Feature::ManageBudgets);

                $input['budgets'] = $this->budgetAmountsByCurrency($team, $request->get('budgets'));
            }

            // Validate with the rules the web form uses.
            $validated = $request->merge($input)->validate([
                'name' => $this->tagNameRules($team, $tag),
                ...$this->budgetRules($team),
            ]);

            // Rename the tag and move its running budget
            // amounts, leaving past months as they were.
            $updateTag->for($team)
                ->by($user)
                ->using($tag, $this->validatedOnly($validated, ['name']), $this->budgetsFrom($team, $validated['budgets'] ?? null));

            return Response::structured(['tag' => $this->presentTag($team, $tag->refresh())]);
        });
    }
}
