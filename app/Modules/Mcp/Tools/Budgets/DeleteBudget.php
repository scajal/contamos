<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Budgets;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\DeleteBudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
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
 * Removes one of the team's budget periods outright.
 */
#[Name('delete_budget')]
#[Description('Delete a budget period outright, leaving the months it covered without a budget. To stop a budget from next month on while keeping its history, use `set_budget_paused` instead. Needs a plan with budgets.')]
#[IsDestructive]
class DeleteBudget extends Tool
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
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, DeleteBudgetPeriod $deleteBudgetPeriod): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $deleteBudgetPeriod): ResponseFactory {
            $this->ensureFeature($team, Feature::ManageBudgets);

            $budget = $this->budgetOf($team, $request->get('budget'));

            // Remove the period outright.
            $deleteBudgetPeriod->for($team)
                ->using($budget);

            return Response::structured(['deleted' => $budget->getKey()]);
        });
    }
}
