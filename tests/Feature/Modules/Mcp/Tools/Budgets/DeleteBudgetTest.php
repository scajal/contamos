<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Budgets\DeleteBudget;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Budgets\DeleteBudget.
 */
it('deletes a budget period of the team', function (): void {
    [$user, $team] = mcpMember();
    $budget = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for($team)->create();

    ContamosServer::actingAs($user)
        ->tool(DeleteBudget::class, ['team' => $team->slug, 'budget' => $budget->getKey()])
        ->assertOk();

    expect(BudgetPeriod::query()->whereKey($budget->getKey())->exists())->toBeFalse();
});

it('does not find a budget of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = BudgetPeriod::factory()->running()->forCategory(Category::Groceries)->for(Team::factory())->create();

    ContamosServer::actingAs($user)
        ->tool(DeleteBudget::class, ['team' => $team->slug, 'budget' => $foreign->getKey()])
        ->assertHasErrors(['Budget not found']);

    expect(BudgetPeriod::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
});

it('is a destructive tool', function (): void {
    expect(new DeleteBudget()->annotations())->toMatchArray(['destructiveHint' => true]);
});
