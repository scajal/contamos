<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Budgets\ListBudgets;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Budgets\ListBudgets.
 */
it('reports the current month progress of the running budget', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    $account = Account::factory()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Vacation']);
    $budget = BudgetPeriod::factory()->running()->forTag($tag)->for($currency)->create(['amount' => '500']);
    BudgetPeriod::factory()->paused()->forTag($tag)->for($currency)->create(['amount' => '100', 'until_month' => CarbonImmutable::now()->startOfMonth()->subMonths(3)]);

    $day = CarbonImmutable::now()->startOfMonth()->addDay();
    Transaction::factory()->expense()->for($account)->for($currency)->create(['amount' => '200', 'occurred_at' => $day])->attachTags([$tag->getKey()]);
    Transaction::factory()->income()->for($account)->for($currency)->create(['amount' => '100', 'occurred_at' => $day])->attachTags([$tag->getKey()]);

    ContamosServer::actingAs($user)
        ->tool(ListBudgets::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('budgets_enabled', true)
            ->where('month', CarbonImmutable::now()->format('Y-m'))
            ->has('budgets', 2)
            ->where('budgets.0.id', $budget->getKey())
            ->where('budgets.0.status', 'running')
            ->where('budgets.0.currency', 'USD')
            ->where('budgets.0.target.tag.name', 'Vacation')
            ->where('budgets.0.progress.amount', '500.00')
            ->where('budgets.0.progress.income', '100.00')
            ->where('budgets.0.progress.spent', '200.00')
            ->where('budgets.0.progress.remaining', '400.00')
            ->where('budgets.0.progress.percentage', fn (mixed $value): bool => abs((float) $value - 33.3333) < 0.001)
            ->where('budgets.1.status', 'ended')
            ->where('budgets.1.progress', null)
            ->etc());
});

it('returns the warning threshold of each budget', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Vacation']);
    BudgetPeriod::factory()->running()->withWarningThreshold(80)->forTag($tag)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(ListBudgets::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('budgets.0.warning_threshold', 80)
            ->etc());
});

it('stays readable on a plan without budgets', function (): void {
    [$user, $team] = mcpMember();
    Pennant::for($team)->deactivate(Feature::ManageBudgets);

    ContamosServer::actingAs($user)
        ->tool(ListBudgets::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('budgets_enabled', false)
            ->has('budgets', 0)
            ->etc());
});

it('is a read-only tool', function (): void {
    expect(new ListBudgets()->annotations())->toMatchArray(['readOnlyHint' => true]);
});

it('names the category budget a sub-budget sits under and confines its figures to it', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    $account = Account::factory()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Electricity']);
    $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create(['amount' => '500']);
    $child = BudgetPeriod::factory()->within($parent)->running()->create(['amount' => '200', 'tag_id' => $tag->getKey()]);

    $day = CarbonImmutable::now()->startOfMonth()->addDay();
    Transaction::factory()->expense()->for($account)->for($currency)->create(['amount' => '120', 'category' => Category::Utilities, 'occurred_at' => $day])->attachTags([$tag->getKey()]);
    Transaction::factory()->expense()->for($account)->for($currency)->create(['amount' => '80', 'category' => Category::Housing, 'occurred_at' => $day])->attachTags([$tag->getKey()]);

    ContamosServer::actingAs($user)
        ->tool(ListBudgets::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('budgets', 2)
            ->where('budgets.0.id', $parent->getKey())
            ->where('budgets.0.parent', null)
            ->where('budgets.0.progress.spent', '120.00')
            ->where('budgets.1.id', $child->getKey())
            ->where('budgets.1.parent', $parent->getKey())
            ->where('budgets.1.progress.spent', '120.00')
            ->where('budgets.1.progress.remaining', '80.00')
            ->etc());
});
