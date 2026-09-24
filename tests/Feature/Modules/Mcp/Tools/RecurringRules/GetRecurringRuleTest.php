<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\RecurringRules\GetRecurringRule;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\RecurringRules\GetRecurringRule.
 */
it('shows one of the team\'s rules', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->active()->for($team)->create();
    $rule = RecurringRule::factory()->expense()->failed('No rate')->create([
        'account_id' => $account->getKey(),
        'team_id' => $team->getKey(),
    ]);

    ContamosServer::actingAs($user)
        ->tool(GetRecurringRule::class, ['team' => $team->slug, 'rule' => $rule->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('rule.id', $rule->getKey())
            ->where('rule.status', 'failed')
            ->where('rule.failure_reason', 'No rate')
            ->etc());
});

it('does not find another team\'s rule', function (): void {
    [$user, $team] = mcpMember();
    $foreign = RecurringRule::factory()->expense()->create();

    ContamosServer::actingAs($user)
        ->tool(GetRecurringRule::class, ['team' => $team->slug, 'rule' => $foreign->getKey()])
        ->assertHasErrors(['Recurring rule not found.']);
});
