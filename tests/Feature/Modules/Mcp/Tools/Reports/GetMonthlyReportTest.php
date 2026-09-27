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
use App\Modules\Mcp\Tools\Reports\GetMonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Reports\GetMonthlyReport.
 */
it('reports the month\'s figures the way the monthly page does', function (): void {
    [$user, $team] = mcpMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
    $account = Account::factory()->active()->for($team)->create();
    $food = Tag::factory()->active()->for($team)->create(['name' => 'Food']);
    BudgetPeriod::factory()->running()->forTag($food)->for($peso)->create(['amount' => '500']);
    $month = CarbonImmutable::now()->startOfMonth()->addDays(3);

    Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '50', 'category' => Category::Salary, 'occurred_at' => $month->subMonth()]);
    Transaction::factory()->income()->for($account)->for($peso)->create(['amount' => '100.50', 'category' => Category::Salary, 'occurred_at' => $month]);
    $groceries = Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '30.25', 'category' => Category::Groceries, 'occurred_at' => $month]);
    $groceries->attachTags([$food->getKey()]);

    ContamosServer::actingAs($user)
        ->tool(GetMonthlyReport::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('month', $month->format('Y-m'))
            ->where('earliest_month', $month->subMonth()->format('Y-m'))
            ->where('is_locked', false)
            ->where('budgets_included', true)
            ->has('currencies', 1)
            ->where('currencies.0.currency.code', 'ARS')
            ->where('currencies.0.income.amount', '100.50')
            ->where('currencies.0.income.delta.amount', '50.50')
            ->where('currencies.0.income.delta.percentage', fn (mixed $percentage): bool => (float) $percentage === 101.0)
            ->where('currencies.0.expenses.amount', '30.25')
            ->where('currencies.0.net.amount', '70.25')
            ->where('currencies.0.transfers', null)
            ->has('currencies.0.trend', 2)
            ->where('currencies.0.trend.1.income', '100.50')
            ->where('currencies.0.tags.0.name', 'Food')
            ->where('currencies.0.tags.0.expenses', '30.25')
            ->where('currencies.0.tags.0.budget.amount', '500.00')
            ->where('currencies.0.tags.0.budget.remaining', '469.75')
            ->where('currencies.0.tags.1.id', null)
            ->where('currencies.0.tags.1.income', '100.50')
            ->where('currencies.0.categories', fn (Collection $rows): bool => $rows->firstWhere('category', Category::Groceries->value)['expenses'] === '30.25'
                && $rows->firstWhere('category', Category::Salary->value)['income'] === '100.50')
            ->etc());
});

it('reports the month asked for', function (): void {
    [$user, $team] = mcpMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
    $account = Account::factory()->active()->for($team)->create();
    $month = CarbonImmutable::now()->startOfMonth()->subMonths(2)->addDays(3);
    Transaction::factory()->expense()->for($account)->for($peso)->create(['amount' => '12', 'occurred_at' => $month]);

    ContamosServer::actingAs($user)
        ->tool(GetMonthlyReport::class, ['team' => $team->slug, 'month' => $month->format('Y-m')])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('month', $month->format('Y-m'))
            ->where('currencies.0.expenses.amount', '12.00')
            ->where('currencies.0.expenses.delta', null)
            ->etc());
});

it('refuses a month that has not happened yet', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(GetMonthlyReport::class, ['team' => $team->slug, 'month' => CarbonImmutable::now()->addMonth()->format('Y-m')])
        ->assertHasErrors(['has not happened yet']);
});

it('refuses a malformed month', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(GetMonthlyReport::class, ['team' => $team->slug, 'month' => '2026-13'])
        ->assertHasErrors();
});

it('reports a month before the plan\'s history window as locked', function (): void {
    // A free plan keeps a short history; MCP is switched on for
    // the team alone so the window can be reached.
    [$user, $team] = memberWithTeam();
    Pennant::for($team)->activate(Feature::UseMcp);
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
    $account = Account::factory()->active()->for($team)->create();
    Transaction::factory()->income()->for($account)->for($peso)->create(['occurred_at' => CarbonImmutable::now()->subMonths(9)]);
    $month = CarbonImmutable::now()->subMonths(6)->format('Y-m');

    ContamosServer::actingAs($user)
        ->tool(GetMonthlyReport::class, ['team' => $team->slug, 'month' => $month])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('month', $month)
            ->where('is_locked', true)
            ->where('currencies', [])
            ->etc());
});

it('is a read-only tool', function (): void {
    expect(new GetMonthlyReport()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
