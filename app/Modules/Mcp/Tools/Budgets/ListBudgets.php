<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Budgets;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsBudgets;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lists the team's budget periods with where each stands and
 * how the current month is going against the ones in force,
 * the same figures the Budgets page shows.
 */
#[Name('list_budgets')]
#[Description("List the team's budget periods: each is a monthly amount in one currency on a category or a tag, from one month and optionally until another, with the `warning_threshold` percentage the team wants a warning at (null for none). Each carries its status (running, scheduled, paused, ended) and, for the one in force this month, its progress: amount, income tagged into it, spent, remaining and percentage used. `budgets_enabled` says whether the plan allows changing budgets.")]
#[IsReadOnly]
class ListBudgets extends Tool
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
        return $this->teamSchema($schema);
    }

    /**
     * Handle the tool call. The listing is open on every plan,
     * like the Budgets page.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, fn (Team $team): ResponseFactory => Response::structured([
            'month' => CarbonImmutable::now()->format('Y-m'),
            'budgets_enabled' => Feature::ManageBudgets->enabled($team),
            'budgets' => $this->presentBudgets($team),
        ]));
    }
}
