<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Budgets\UpdateBudget;
use Carbon\CarbonImmutable;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Budgets\UpdateBudget.
 */
it('changes the amount and last month, keeping the rest', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $budget = BudgetPeriod::factory()->running()->forCategory(Category::Food)->for($team)->for($currency)->create(['amount' => '300']);
    $until = CarbonImmutable::now()->addMonths(3)->format('Y-m');

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'amount' => '420', 'until_month' => $until])
        ->assertOk();

    $budget->refresh();

    expect((float) $budget->amount)->toBe(420.0)
        ->and($budget->currency_id)->toBe($currency->getKey())
        ->and($budget->from_month->format('Y-m'))->toBe(CarbonImmutable::now()->format('Y-m'))
        ->and($budget->until_month?->format('Y-m'))->toBe($until);
});

it('does not find a budget of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = BudgetPeriod::factory()->running()->forCategory(Category::Food)->for(Team::factory())->create(['amount' => '300']);

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $foreign->getKey(), 'amount' => '420'])
        ->assertHasErrors(['Budget not found']);

    expect((float) $foreign->refresh()->amount)->toBe(300.0);
});

it('is a destructive tool', function (): void {
    expect(new UpdateBudget()->annotations())->toMatchArray(['destructiveHint' => true]);
});
