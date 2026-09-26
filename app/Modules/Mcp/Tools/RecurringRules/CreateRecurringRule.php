<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\RecurringRules;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateRecurringRule as CreateRecurringRuleAction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
 * Creates a recurring rule, the template and schedule of a
 * transaction the team records again and again.
 */
#[Name('create_recurring_rule')]
#[Description('Create a recurring rule: a transaction template (account, amount, type, category, description, tags) and a schedule (every `interval` `frequency` units from `starts_on`, until `ends_on` if given, at `time_of_day` in `timezone`). Occurrences are generated automatically as they fall due; the first one is not recorded now. Requires a plan with recurring transactions.')]
#[IsDestructive(false)]
class CreateRecurringRule extends Tool
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
            ...$this->recurringRuleSchema($schema, creating: true),
        ];
    }

    /**
     * Handle the tool call, the way the web form stores a rule.
     */
    public function handle(Request $request, CreateRecurringRuleAction $createRecurringRule): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createRecurringRule): ResponseFactory {
            // Only a plan with recurring transactions adds rules.
            $this->ensureFeature($team, Feature::RecurTransactions);

            $input = $this->recurringRuleInput($team, $request);

            // Create the rule, then tag it once it exists.
            $rule = $createRecurringRule->for($team)->by($user)->using($input['attributes']);

            $rule->attachTags($input['tag_ids']);

            return Response::structured(['rule' => $this->presentRecurringRule($rule->refresh())]);
        });
    }
}
