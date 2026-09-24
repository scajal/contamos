<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\RecurringRules;

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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Shows one of the team's recurring rules.
 */
#[Name('get_recurring_rule')]
#[Description("Get one of the team's recurring rules: its template, schedule, `next_due_date`, `status` (active, paused or failed) and, when failing, why.")]
#[IsReadOnly]
class GetRecurringRule extends Tool
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
     * Handle the tool call.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, fn (Team $team): ResponseFactory => Response::structured([
            'rule' => $this->presentRecurringRule($this->findRule($team, $request)),
        ]));
    }
}
