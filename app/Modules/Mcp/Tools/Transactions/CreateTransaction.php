<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Finance\Actions\Team\CreateTransactionEntry;
use App\Modules\Core\Finance\Actions\Team\EvaluateBudgetAlerts;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\PresentsTransactions;
use App\Modules\Mcp\Tools\Concerns\ResolvesTransactionArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Records an income or an expense on one of the team's
 * accounts, optionally repeating on a schedule.
 */
#[Name('create_transaction')]
#[Description("Record an income or an expense on one of the team's accounts and move its balance. The currency is inferred when the account holds only one; the exchange rate defaults to the one recorded for the date. Set `repeat` with a `recurring_frequency` to make it repeat (Pro plan): a recurring rule is created and the first occurrence is recorded now only when it is due by today. For money moved between the team's own accounts use `create_transfer`.")]
class CreateTransaction extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;
    use PresentsTransactions;
    use ResolvesTransactionArguments;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            ...$this->transactionArgumentsSchema($schema),
            ...$this->repeatArgumentsSchema($schema),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, CreateTransactionEntry $createTransactionEntry, EvaluateBudgetAlerts $evaluateBudgetAlerts): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createTransactionEntry, $evaluateBudgetAlerts): ResponseFactory {
            $timezone = $this->timezone($request->get('timezone'));

            // Resolve and validate the arguments like the web form.
            [$attributes, $tagIds] = $this->transactionAttributes($team, $request->all());

            // Record the entry, creating its recurring rule when it
            // repeats and its first occurrence once it is due.
            ['transaction' => $transaction, 'recurringRule' => $rule] = $createTransactionEntry
                ->for($team)
                ->by($user)
                ->using($attributes, $tagIds);

            // Alert about the budgets the new movement pushed past
            // a threshold.
            $evaluateBudgetAlerts->for($team)
                ->using([$transaction?->occurred_at]);

            return Response::structured([
                'transaction' => $transaction instanceof Transaction
                    ? $this->presentTransaction($transaction->load('account', 'currency', 'tags', 'user'), $timezone)
                    : null,
                'recurring_rule' => $rule instanceof RecurringRule ? [
                    'id' => $rule->getKey(),
                    'frequency' => $rule->frequency->value,
                    'interval' => $rule->interval,
                    'starts_on' => $rule->starts_on->toDateString(),
                    'ends_on' => $rule->ends_on?->toDateString(),
                    'time_of_day' => $rule->time_of_day,
                    'timezone' => $rule->timezone,
                ] : null,
            ]);
        });
    }
}
