<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\RecurringRules;

use App\Modules\Core\Finance\Models\Team\RecurringRule;
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
 * Lists the team's recurring rules by when each one fires next,
 * the way the web listing orders them.
 */
#[Name('list_recurring_rules')]
#[Description("List the team's recurring rules, soonest next occurrence first (rules with no occurrence left come last), each with its template, schedule, `next_due_date` and `status` (active, paused or failed). Pages hold 50 rules; pass `page` for the next ones while `has_more` is true.")]
#[IsReadOnly]
class ListRecurringRules extends Tool
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
            'page' => $schema->integer()
                ->min(1)
                ->description('The page to fetch, from 1. Leave out for the first page.'),
        ];
    }

    /**
     * Handle the tool call. The next due date is computed from
     * each rule's schedule rather than stored, so the team's
     * rules are ordered in PHP, like on the web.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request): ResponseFactory {
            $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

            $page = is_numeric($page = $request->get('page')) ? (int) $page : 1;

            // Order by the next due date, with the key as a stable
            // tiebreaker between rules due the same day.
            $rules = $team->recurringRules()
                ->with(['account', 'currency', 'tags'])
                ->orderBy(new RecurringRule()->getKeyName())
                ->get()
                ->sortBy(fn (RecurringRule $rule): string => $rule->nextDueSortKey())
                ->values();

            return Response::structured([
                'items' => $rules->forPage($page, $this->perPage)
                    ->map(fn (RecurringRule $rule): array => $this->presentRecurringRule($rule))
                    ->values()
                    ->all(),
                'page' => $page,
                'has_more' => $rules->count() > $page * $this->perPage,
            ]);
        });
    }
}
