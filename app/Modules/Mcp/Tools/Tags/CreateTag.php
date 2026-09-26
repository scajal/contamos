<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Tags;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateTag as CreateTagAction;
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
 * Creates a tag on the team, optionally with a monthly budget
 * per currency from this month on.
 */
#[Name('create_tag')]
#[Description('Create a tag on the team. The name must be unused on the team (case does not matter). On plans with budgets, `budgets` also sets its monthly budget per currency from this month on.')]
#[IsDestructive(false)]
class CreateTag extends Tool
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
            'name' => $schema->string()
                ->description("The tag's name.")
                ->required(),
            ...$this->tagBudgetsSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, CreateTagAction $createTag): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createTag): ResponseFactory {
            $input = ['name' => $request->get('name')];

            // Budgets are only offered on a plan that has them,
            // keyed by currency the way the web form sends them.
            if ($request->get('budgets') !== null) {
                $this->ensureFeature($team, Feature::ManageBudgets);

                $input['budgets'] = $this->budgetAmountsByCurrency($team, $request->get('budgets'));
            }

            // Validate with the rules the web form uses.
            $validated = $request->merge($input)->validate([
                'name' => $this->tagNameRules($team),
                ...$this->budgetRules($team),
            ]);

            // Create the tag, opening a budget from this month
            // for every currency an amount came in for.
            $tag = $createTag->for($team)
                ->by($user)
                ->using($this->validatedOnly($validated, ['name']), $this->budgetsFrom($team, $validated['budgets'] ?? null));

            return Response::structured(['tag' => $this->presentTag($team, $tag)]);
        });
    }
}
