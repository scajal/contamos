<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Budgets\SetBudgetPaused;
use Carbon\CarbonImmutable;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Budgets\SetBudgetPaused.
 */

/**
 * Create an MCP member of a pro team holding USD on an active
 * account.
 *
 * @return array{0: \App\Modules\Core\Identity\Models\User, 1: \App\Modules\Core\Identity\Models\Team, 2: \App\Modules\Core\Catalog\Models\Currency}
 */
function pausingBudgetTeam(): array
{
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);

    return [$user, $team, $currency];
}

it('pauses a running budget at the end of the current month', function (): void {
    [$user, $team, $currency] = pausingBudgetTeam();
    $budget = BudgetPeriod::factory()->forCategory(Category::Groceries)->for($team)->for($currency)->create([
        'from_month' => CarbonImmutable::now()->startOfMonth()->subMonths(2),
        'until_month' => null,
    ]);

    ContamosServer::actingAs($user)
        ->tool(SetBudgetPaused::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'paused' => true])
        ->assertOk();

    expect($budget->refresh()->until_month?->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'))
        ->and($team->budgets()->count())->toBe(1);
});

it('resumes a paused budget from this month on the same amount', function (): void {
    [$user, $team, $currency] = pausingBudgetTeam();
    $budget = BudgetPeriod::factory()->paused()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '750']);

    ContamosServer::actingAs($user)
        ->tool(SetBudgetPaused::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'paused' => false])
        ->assertOk()
        ->assertSee('running');

    $resumed = $team->budgets()->whereKeyNot($budget)->sole();

    expect($resumed->category)->toBe(Category::Groceries)
        ->and((float) $resumed->amount)->toBe(750.0)
        ->and($resumed->currency_id)->toBe($currency->getKey())
        ->and($resumed->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'))
        ->and($resumed->until_month)->toBeNull()
        ->and($budget->refresh()->until_month?->format('Y-m'))->toBe(CarbonImmutable::now()->subMonth()->format('Y-m'));
});

it('refuses to resume a budget that is running', function (): void {
    [$user, $team, $currency] = pausingBudgetTeam();
    $budget = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(SetBudgetPaused::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'paused' => false])
        ->assertHasErrors(['Only a paused budget can be resumed']);

    expect($team->budgets()->count())->toBe(1);
});

it('refuses to pause a budget that is not running open-ended', function (): void {
    [$user, $team, $currency] = pausingBudgetTeam();
    $budget = BudgetPeriod::factory()->paused()->forCategory(Category::Groceries)->for($team)->for($currency)->create();
    $untilMonth = $budget->until_month?->format('Y-m');

    ContamosServer::actingAs($user)
        ->tool(SetBudgetPaused::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'paused' => true])
        ->assertHasErrors(['Only a running budget with no last month can be paused. This one is paused.']);

    expect($budget->refresh()->until_month?->format('Y-m'))->toBe($untilMonth);
});

it('is a destructive tool', function (): void {
    expect(new SetBudgetPaused()->annotations())->toMatchArray(['destructiveHint' => true]);
});
