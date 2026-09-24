<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * Asks the assistant for a concise review of a team's month,
 * built from the monthly report and the budgets.
 */
#[Name('monthly_review')]
#[Description("Get a concise review of a team's month: totals against last month, top categories and anything over budget.")]
class MonthlyReview extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $team = is_scalar($value = $request->get('team')) ? (string) $value : '';
        $month = is_scalar($value = $request->get('month')) ? (string) $value : '';

        $monthArgument = $month !== '' ? ", month: \"{$month}\"" : '';
        $monthName = $month !== '' ? $month : 'the current month';

        return Response::text(<<<MARKDOWN
            Review {$monthName} for the Contamos team `{$team}`.

            1. Call `get_monthly_report` with `team: "{$team}"{$monthArgument}`, and `list_budgets` with `team: "{$team}"`.
            2. Write a concise review, currency by currency: income, expenses and net against the previous month, the top expense categories, and every budget that is over or close to its amount.
            3. Keep amounts exact as the tools return them, and do not invent figures the tools did not return. If the month is locked or has no activity, say so.
            MARKDOWN);
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('team', 'The slug of the team, as listed by `list_teams`.', required: true),
            new Argument('month', 'The month to review, "YYYY-MM". Defaults to the current month.'),
        ];
    }
}
