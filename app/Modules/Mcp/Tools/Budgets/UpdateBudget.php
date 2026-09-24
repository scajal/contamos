<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Budgets;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\UpdateBudgetPeriod;
use App\Modules\Core\Finance\Http\Requests\Team\Budget\Concerns\ResolvesBudgetInputs;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
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
 * Changes the amount, currency, months or warning threshold
 * of one of the team's budget periods. What it follows never
 * changes.
 */
#[Name('update_budget')]
#[Description('Change a budget period\'s amount, currency, months ("YYYY-MM") or `warning_threshold` (1 to 99). Leave out what should not change; pass `until_month` as null to make it open-ended and `warning_threshold` as null to drop the warning. Its category or tag cannot change: create a new budget instead. Needs a plan with budgets.')]
#[IsDestructive]
class UpdateBudget extends Tool
{
    use InteractsWithTeams;
    use PresentsBudgets;
    use PresentsRecords;
    use ResolvesBudgetInputs;

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
            'amount' => $schema->string()
                ->description('The new monthly amount, as a decimal string.'),
            'currency' => $schema->string()
                ->description('The ISO code of the new currency, one the team holds on an active account.'),
            'from_month' => $schema->string()
                ->description('The new first month, "YYYY-MM".'),
            'until_month' => $schema->string()
                ->nullable()
                ->description('The new last month, "YYYY-MM", or null for no last month.'),
            'warning_threshold' => $schema->integer()
                ->min(1)
                ->max(99)
                ->nullable()
                ->description("The new percentage of the month's budget, from 1 to 99, at which the team wants a warning, or null for no warning."),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, UpdateBudgetPeriod $updateBudgetPeriod): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $updateBudgetPeriod): ResponseFactory {
            $this->ensureFeature($team, Feature::ManageBudgets);

            $budget = $this->budgetOf($team, $request->get('budget'));
            $currencyKey = $budget->currency()->getForeignKeyName();
            $arguments = $request->all();

            // Whatever was left out keeps its current value, then
            // the web form's rules check the whole.
            $amount = $arguments['amount'] ?? (string) $budget->amount;

            $input = [
                'amount' => is_int($amount) || is_float($amount) ? (string) $amount : $amount,
                $currencyKey => array_key_exists('currency', $arguments)
                    ? $this->currencyByCode($arguments['currency'])->getKey()
                    : $budget->getAttribute($currencyKey),
                'from_month' => $arguments['from_month'] ?? $budget->from_month->format(self::MONTH_FORMAT),
                'until_month' => array_key_exists('until_month', $arguments)
                    ? $arguments['until_month']
                    : $budget->until_month?->format(self::MONTH_FORMAT),
                'warning_threshold' => array_key_exists('warning_threshold', $arguments)
                    ? $arguments['warning_threshold']
                    : $budget->warning_threshold,
            ];

            $validated = $request->merge($input)->validate(
                $this->budgetUpdateRules($team, $budget, $input),
                [],
                [$currencyKey => 'currency'],
            );

            // Change the period's amount, currency, months or
            // warning threshold.
            $updateBudgetPeriod->for($team)
                ->by($user)
                ->using($budget, $this->validatedOnly($validated, ['amount', $currencyKey, 'from_month', 'until_month', 'warning_threshold']));

            return Response::structured(['budget' => $this->presentOneBudget($team, $budget->refresh())]);
        });
    }
}
