<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Budgets;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Actions\Team\CreateBudgetPeriod;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Http\Requests\Team\Budget\Concerns\ResolvesBudgetInputs;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsBudgets;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\ResolvesTagReferences;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Opens a budget period on a category or on one of the team's
 * tags.
 */
#[Name('create_budget')]
#[Description('Set a monthly budget on a category or a tag (pass exactly one) in one currency the team holds, from `from_month` and optionally until `until_month` (both "YYYY-MM", inclusive). Months may not overlap another budget on the same target and currency. Optionally pass `warning_threshold` (1 to 99) to be warned once the month has used that percentage. Needs a plan with budgets.')]
#[IsDestructive(false)]
class CreateBudget extends Tool
{
    use InteractsWithTeams;
    use PresentsBudgets;
    use PresentsRecords;
    use ResolvesBudgetInputs;
    use ResolvesTagReferences;

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'category' => $schema->string()
                ->enum(Category::class)
                ->description('The category to budget. Pass this or `tag`.'),
            'tag' => $schema->string()
                ->description('The id or exact name of the tag to budget, as listed by `list_tags`. Pass this or `category`.'),
            'amount' => $schema->string()
                ->description('The monthly amount, as a decimal string such as "500" or "1250.50".')
                ->required(),
            'currency' => $schema->string()
                ->description('The ISO code of the currency, one the team holds on an active account.')
                ->required(),
            'from_month' => $schema->string()
                ->description('The first month, "YYYY-MM".')
                ->required(),
            'until_month' => $schema->string()
                ->description('The last month, "YYYY-MM". Leave out for a budget that never ends.'),
            'warning_threshold' => $schema->integer()
                ->min(1)
                ->max(99)
                ->nullable()
                ->description("The percentage of the month's budget, from 1 to 99, at which the team wants a warning. Leave out for no warning."),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, CreateBudgetPeriod $createBudgetPeriod): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createBudgetPeriod): ResponseFactory {
            $this->ensureFeature($team, Feature::ManageBudgets);

            $period = new BudgetPeriod;
            $currencyKey = $period->currency()->getForeignKeyName();
            $tagKey = $period->tag()->getForeignKeyName();

            // A budget follows exactly one target.
            $category = $request->get('category');
            $reference = $request->get('tag');

            if (($category === null) === ($reference === null)) {
                throw new ToolError('Pass either `category` or `tag`, not both and not neither.');
            }

            $tag = $reference !== null ? $this->tagOf($team, $reference) : null;

            // Map the arguments onto the fields the web form
            // submits, then validate with its rules.
            $input = [
                'amount' => is_int($amount = $request->get('amount')) || is_float($amount) ? (string) $amount : $amount,
                $currencyKey => $this->currencyByCode($request->get('currency'))->getKey(),
                'from_month' => $request->get('from_month'),
                'until_month' => $request->get('until_month'),
                'warning_threshold' => $request->get('warning_threshold'),
                'target_kind' => $tag instanceof \App\Modules\Core\Finance\Models\Team\Tag ? BudgetTargetKind::Tag->value : BudgetTargetKind::Category->value,
                ...($tag instanceof \App\Modules\Core\Finance\Models\Team\Tag ? [$tagKey => $tag->getKey()] : ['category' => $category]),
            ];

            $validated = $request->merge($input)->validate(
                $this->budgetStoreRules($team, $input),
                [],
                [$currencyKey => 'currency', $tagKey => 'tag'],
            );

            // Open the period on the chosen target.
            $budget = $createBudgetPeriod->for($team)
                ->targeting($tag ?? Category::from($this->text($validated['category'])))
                ->by($user)
                ->using($this->validatedOnly($validated, ['amount', $currencyKey, 'from_month', 'until_month', 'warning_threshold']));

            return Response::structured(['budget' => $this->presentOneBudget($team, $budget)]);
        });
    }
}
