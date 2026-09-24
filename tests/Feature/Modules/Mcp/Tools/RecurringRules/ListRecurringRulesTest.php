<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\RecurringRules\ListRecurringRules;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\RecurringRules\ListRecurringRules.
 */
it('is read-only', function (): void {
    expect(new ListRecurringRules()->annotations())->toMatchArray(['readOnlyHint' => true]);
});

it('lists the team\'s rules soonest first, with next due date and status', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->active()->for($team)->create();

    $later = RecurringRule::factory()->expense()->create([
        'account_id' => $account->getKey(),
        'starts_on' => CarbonImmutable::now()->addMonth()->toDateString(),
        'team_id' => $team->getKey(),
    ]);
    $sooner = RecurringRule::factory()->expense()->paused()->create([
        'account_id' => $account->getKey(),
        'starts_on' => CarbonImmutable::now()->addDay()->toDateString(),
        'team_id' => $team->getKey(),
    ]);
    RecurringRule::factory()->expense()->create();

    ContamosServer::actingAs($user)
        ->tool(ListRecurringRules::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 2)
            ->where('items.0.id', $sooner->getKey())
            ->where('items.0.status', 'paused')
            ->where('items.0.next_due_date', CarbonImmutable::now()->addDay()->toDateString())
            ->where('items.1.id', $later->getKey())
            ->where('has_more', false)
            ->etc());
});
