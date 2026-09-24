<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\RecurringRules\DeleteRecurringRule;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\RecurringRules\DeleteRecurringRule.
 */
it('is destructive', function (): void {
    expect(new DeleteRecurringRule()->annotations())->toMatchArray(['destructiveHint' => true]);
});

it('deletes the rule and keeps the transactions it generated', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->active()->for($team)->create();
    $rule = RecurringRule::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'team_id' => $team->getKey(),
    ]);
    $transaction = Transaction::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'currency_id' => $rule->currency_id,
        'due_date' => '2026-05-15',
        'recurring_rule_id' => $rule->getKey(),
        'team_id' => $team->getKey(),
    ]);

    ContamosServer::actingAs($user)
        ->tool(DeleteRecurringRule::class, ['team' => $team->slug, 'rule' => $rule->getKey()])
        ->assertOk()
        ->assertStructuredContent(['deleted' => true, 'id' => $rule->getKey()]);

    expect(RecurringRule::query()->whereKey($rule->getKey())->exists())->toBeFalse()
        ->and($transaction->refresh()->recurring_rule_id)->toBeNull();
});

it('refuses a plan without recurring transactions and keeps the rule', function (): void {
    [$user, $team] = mcpMember();
    Pennant::for($team)->deactivate(Feature::RecurTransactions);
    $rule = RecurringRule::factory()->expense()->create(['team_id' => $team->getKey()]);

    ContamosServer::actingAs($user)
        ->tool(DeleteRecurringRule::class, ['team' => $team->slug, 'rule' => $rule->getKey()])
        ->assertHasErrors(['Upgrade to Pro']);

    expect(RecurringRule::query()->whereKey($rule->getKey())->exists())->toBeTrue();
});

it('does not find another team\'s rule', function (): void {
    [$user, $team] = mcpMember();
    $foreign = RecurringRule::factory()->expense()->create();

    ContamosServer::actingAs($user)
        ->tool(DeleteRecurringRule::class, ['team' => $team->slug, 'rule' => $foreign->getKey()])
        ->assertHasErrors(['Recurring rule not found.']);

    expect(RecurringRule::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
});
