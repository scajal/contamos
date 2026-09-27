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
    $budget = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->for($currency)->create(['amount' => '300']);
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

it('changes the warning threshold', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $budget = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forCategory(Category::Groceries)->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'warning_threshold' => 90])
        ->assertOk()
        ->assertSee('"warning_threshold":90');

    expect($budget->refresh()->warning_threshold)->toBe(90);
});

it('drops the warning threshold when it is null', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $budget = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forCategory(Category::Groceries)->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'warning_threshold' => null])
        ->assertOk();

    expect($budget->refresh()->warning_threshold)->toBeNull();
});

it('keeps the warning threshold when it is left out', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $budget = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forCategory(Category::Groceries)->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'amount' => '420'])
        ->assertOk();

    expect($budget->refresh()->warning_threshold)->toBe(80);
});

it('refuses a warning threshold of a hundred', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $budget = BudgetPeriod::factory()->running()->withWarningThreshold(80)->forCategory(Category::Groceries)->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'warning_threshold' => 100])
        ->assertHasErrors(['warning threshold']);

    expect($budget->refresh()->warning_threshold)->toBe(80);
});

it('does not find a budget of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for(Team::factory())->create(['amount' => '300']);

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $foreign->getKey(), 'amount' => '420'])
        ->assertHasErrors(['Budget not found']);

    expect((float) $foreign->refresh()->amount)->toBe(300.0);
});

it('is a destructive tool', function (): void {
    expect(new UpdateBudget()->annotations())->toMatchArray(['destructiveHint' => true]);
});

it('moves a tag budget under a category budget and back out', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();
    $budget = BudgetPeriod::factory()->running()->forTag()->for($team)->for($currency)->create();

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'parent' => $parent->getKey()])
        ->assertOk()
        ->assertSee('"parent":"'.$parent->getKey().'"');

    expect($budget->refresh()->parent_id)->toBe($parent->getKey());

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'parent' => null])
        ->assertOk()
        ->assertSee('"parent":null');

    expect($budget->refresh()->parent_id)->toBeNull();
});

it('keeps the parent when the change says nothing about it', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $parent = BudgetPeriod::factory()->running()->forCategory(Category::Utilities)->for($team)->for($currency)->create();
    $budget = BudgetPeriod::factory()->within($parent)->running()->create(['amount' => '100']);

    ContamosServer::actingAs($user)
        ->tool(UpdateBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey(), 'amount' => '150'])
        ->assertOk();

    expect($budget->refresh()->parent_id)->toBe($parent->getKey());
});
