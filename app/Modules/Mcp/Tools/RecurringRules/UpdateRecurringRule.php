<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\RecurringRules;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\UpdateRecurringRule as UpdateRecurringRuleAction;
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
 * Changes a recurring rule's template or schedule from its next
 * occurrence on.
 */
#[Name('update_recurring_rule')]
#[Description("Change a recurring rule's template or schedule. Only the arguments given change; the rest stays. The change applies from the next occurrence on: transactions already generated are left as they were. Clears a recorded failure. Requires a plan with recurring transactions.")]
#[IsDestructive]
class UpdateRecurringRule extends Tool
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
            ...$this->recurringRuleSchema($schema, creating: false),
        ];
    }

    /**
     * Handle the tool call, the way the web form updates a rule.
     */
    public function handle(Request $request, UpdateRecurringRuleAction $updateRecurringRule): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $updateRecurringRule): ResponseFactory {
            // Only a plan with recurring transactions edits rules.
            $this->ensureFeature($team, Feature::RecurTransactions);

            $rule = $this->findRule($team, $request);

            $input = $this->recurringRuleInput($team, $request, $rule);

            // Apply the change from the next occurrence on.
            $updateRecurringRule->for($team)->using($rule, $input['attributes'], $input['tag_ids']);

            return Response::structured(['rule' => $this->presentRecurringRule($rule->refresh())]);
        });
    }
}
