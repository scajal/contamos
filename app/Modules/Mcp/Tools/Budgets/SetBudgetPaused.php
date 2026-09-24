<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Budgets;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod;
use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsBudgets;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Pauses or resumes one of the team's budgets, the way the
 * Budgets page does.
 */
#[Name('set_budget_paused')]
#[Description('Pause (true) a running open-ended budget: it keeps this month and stops after it. Resume (false) a paused budget: a new open-ended period starts this month on the same amount and warning threshold. Needs a plan with budgets.')]
#[IsDestructive]
class SetBudgetPaused extends Tool
{
    use InteractsWithTeams;
    use PresentsBudgets;
    use PresentsRecords;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            ...$this->budgetSchema($schema),
            'paused' => $schema->boolean()
                ->description('True to pause the budget, false to resume it.')
                ->required(),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, UpdateBudgetPeriod $updateBudgetPeriod, CreateBudgetPeriod $createBudgetPeriod): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $updateBudgetPeriod, $createBudgetPeriod): ResponseFactory {
            $this->ensureFeature($team, Feature::ManageBudgets);

            $budget = $this->budgetOf($team, $request->get('budget'));

            $validated = $request->validate([
                'paused' => ['required', 'boolean'],
            ]);

            $status = $this->currentBudgetStatus($team, $budget);

            // Only an open-ended stretch in force can be stopped,
            // as on the Budgets page.
            if ((bool) $validated['paused']) {
                if (! $this->canPauseBudget($team, $budget)) {
                    throw new ToolError('Only a running budget with no last month can be paused. This one is '.$status->value.'.');
                }

                $updateBudgetPeriod->for($team)
                    ->by($user)
                    ->pause($budget);

                return Response::structured(['budget' => $this->presentOneBudget($team, $budget->refresh())]);
            }

            // Only a stretch nothing followed can be picked back up.
            if (! $this->canResumeBudget($team, $budget)) {
                throw new ToolError('Only a paused budget can be resumed. This one is '.$status->value.'.');
            }

            $resumed = $createBudgetPeriod->for($team)
                ->by($user)
                ->resume($budget);

            return Response::structured(['budget' => $this->presentOneBudget($team, $resumed)]);
        });
    }
}
