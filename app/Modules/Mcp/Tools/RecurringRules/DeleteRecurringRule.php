<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\RecurringRules;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\DeleteRecurringRule as DeleteRecurringRuleAction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithRecurringRules;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
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
 * Deletes a recurring rule, keeping what it generated.
 */
#[Name('delete_recurring_rule')]
#[Description('Delete a recurring rule so it generates nothing more. The transactions it already generated are kept as ordinary transactions. This cannot be undone.')]
#[IsDestructive]
class DeleteRecurringRule extends Tool
{
    use InteractsWithRecurringRules;
    use InteractsWithTeams;
    use NormalizesInputs;
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
            ...$this->ruleSchema($schema),
        ];
    }

    /**
     * Handle the tool call. Like on the web, deleting a rule
     * needs the team's plan to include recurring transactions.
     */
    public function handle(Request $request, DeleteRecurringRuleAction $deleteRecurringRule): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $deleteRecurringRule): ResponseFactory {
            // Recurring rules are a Pro feature, as on the web.
            $this->ensureFeature($team, Feature::RecurTransactions);

            $rule = $this->findRule($team, $request);

            $id = $rule->getKey();

            // Delete the rule; its transactions stay in the ledger.
            $deleteRecurringRule->for($team)->using($rule);

            return Response::structured(['deleted' => true, 'id' => $id]);
        });
    }
}
