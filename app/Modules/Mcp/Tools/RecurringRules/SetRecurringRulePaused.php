<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\RecurringRules;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\PauseRecurringRule;
use App\Modules\Core\Finance\Actions\Team\ResumeRecurringRule;
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
 * Pauses or resumes a recurring rule.
 */
#[Name('set_recurring_rule_paused')]
#[Description('Pause (`paused: true`) or resume (`paused: false`) a recurring rule. A paused rule generates nothing. Resuming picks up at the next due date after today: occurrences that fell due while paused are skipped, never backfilled. A rule already in the requested state is left as is. Requires a plan with recurring transactions.')]
#[IsDestructive]
class SetRecurringRulePaused extends Tool
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
            'paused' => $schema->boolean()
                ->description('True to pause the rule, false to resume it.')
                ->required(),
        ];
    }

    /**
     * Handle the tool call, the way the web pauses and resumes.
     */
    public function handle(Request $request, PauseRecurringRule $pauseRecurringRule, ResumeRecurringRule $resumeRecurringRule): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $pauseRecurringRule, $resumeRecurringRule): ResponseFactory {
            // Only a plan with recurring transactions restarts
            // or suspends rules.
            $this->ensureFeature($team, Feature::RecurTransactions);

            $request->validate(['paused' => ['required', 'boolean']]);

            $rule = $this->findRule($team, $request);

            $paused = filter_var($request->get('paused'), FILTER_VALIDATE_BOOLEAN);

            // A rule already in the requested state is left alone,
            // so resuming a running rule never skips a retry.
            if ($paused && $rule->is_active) {
                $pauseRecurringRule->for($team)->using($rule);
            } elseif (! $paused && ! $rule->is_active) {
                $resumeRecurringRule->for($team)->using($rule);
            }

            return Response::structured(['rule' => $this->presentRecurringRule($rule->refresh())]);
        });
    }
}
