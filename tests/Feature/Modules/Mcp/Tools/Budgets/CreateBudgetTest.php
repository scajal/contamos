<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Budgets\CreateBudget;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Budgets\CreateBudget.
 */

/**
 * Create an MCP member of a pro team holding USD on an active
 * account.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team, 2: \App\Modules\Core\Catalog\Models\Currency}
 */
function createBudgetTeam(): array
{
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);

    return [$user, $team, $currency];
}

it('budgets a category', function (): void {
    [$user, $team, $currency] = createBudgetTeam();
    $month = CarbonImmutable::now()->format('Y-m');

    ContamosServer::actingAs($user)
        ->tool(CreateBudget::class, [
            'team' => $team->slug,
            'category' => Category::Food->value,
            'amount' => '800',
            'currency' => 'usd',
            'from_month' => $month,
        ])
        ->assertOk()
        ->assertSee('running');

    $period = $team->budgets()->sole();

    expect($period->category)->toBe(Category::Food)
        ->and($period->tag_id)->toBeNull()
        ->and((float) $period->amount)->toBe(800.0)
        ->and($period->currency_id)->toBe($currency->getKey())
        ->and($period->from_month->format('Y-m'))->toBe($month)
        ->and($period->until_month)->toBeNull();
});

it('budgets a tag found by name', function (): void {
    [$user, $team] = createBudgetTeam();
    $tag = Tag::factory()->active()->for($team)->create(['name' => 'Vacation']);
    $from = CarbonImmutable::now()->addMonth();

    ContamosServer::actingAs($user)
        ->tool(CreateBudget::class, [
            'team' => $team->slug,
            'tag' => 'vacation',
            'amount' => '1500',
            'currency' => 'USD',
            'from_month' => $from->format('Y-m'),
            'until_month' => $from->addMonths(2)->format('Y-m'),
        ])
        ->assertOk();

    $period = $team->budgets()->sole();

    expect($period->tag_id)->toBe($tag->getKey())
        ->and($period->category)->toBeNull()
        ->and($period->until_month?->format('Y-m'))->toBe($from->addMonths(2)->format('Y-m'));
});

it('refuses months another budget on the target already covers', function (): void {
    [$user, $team, $currency] = createBudgetTeam();
    BudgetPeriod::factory()->running()->forCategory(Category::Food)->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(CreateBudget::class, [
            'team' => $team->slug,
            'category' => Category::Food->value,
            'amount' => '800',
            'currency' => 'USD',
            'from_month' => CarbonImmutable::now()->format('Y-m'),
        ])
        ->assertHasErrors(['already has a budget covering those months']);

    expect($team->budgets()->count())->toBe(1);
});

it('does not find a tag of another team', function (): void {
    [$user, $team] = createBudgetTeam();
    $foreign = Tag::factory()->active()->for(Team::factory())->create();

    ContamosServer::actingAs($user)
        ->tool(CreateBudget::class, [
            'team' => $team->slug,
            'tag' => $foreign->getKey(),
            'amount' => '800',
            'currency' => 'USD',
            'from_month' => CarbonImmutable::now()->format('Y-m'),
        ])
        ->assertHasErrors(['Tag not found']);

    expect(BudgetPeriod::query()->count())->toBe(0);
});

it('refuses a plan without budgets', function (): void {
    [$user, $team] = createBudgetTeam();
    Pennant::for($team)->deactivate(Feature::ManageBudgets);

    ContamosServer::actingAs($user)
        ->tool(CreateBudget::class, [
            'team' => $team->slug,
            'category' => Category::Food->value,
            'amount' => '800',
            'currency' => 'USD',
            'from_month' => CarbonImmutable::now()->format('Y-m'),
        ])
        ->assertHasErrors(['Budgets']);

    expect($team->budgets()->count())->toBe(0);
});
