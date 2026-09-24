<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Reports\GetBalances;
use Carbon\CarbonImmutable;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Reports\GetBalances.
 */
it('sums each currency across the team\'s active accounts', function (): void {
    [$user, $team] = mcpMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS', 'decimal_places' => 2]);
    $dollar = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    $bank = Account::factory()->active()->for($team)->create();
    $cash = Account::factory()->active()->for($team)->create();
    $archived = Account::factory()->inactive()->for($team)->create();
    $longAgo = CarbonImmutable::now()->subYears(3);

    Transaction::factory()->income()->for($bank)->for($peso)->create(['amount' => '100.50', 'occurred_at' => $longAgo]);
    Transaction::factory()->expense()->for($cash)->for($peso)->create(['amount' => '30.25']);
    Transaction::factory()->income()->for($cash)->for($dollar)->create(['amount' => '10']);
    Transaction::factory()->income()->for($archived)->for($dollar)->create(['amount' => '999']);

    ContamosServer::actingAs($user)
        ->tool(GetBalances::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent([
            'balances' => [
                ['currency' => 'ARS', 'balance' => '70.25'],
                ['currency' => 'USD', 'balance' => '10.00'],
            ],
        ]);
});

it('never shows another team\'s money', function (): void {
    [$user, $team] = mcpMember();
    [, $other] = mcpMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
    Transaction::factory()->income()->for(Account::factory()->active()->for($other)->create())->for($peso)->create(['amount' => '50']);

    ContamosServer::actingAs($user)
        ->tool(GetBalances::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(['balances' => []]);
});

it('is a read-only tool', function (): void {
    expect(new GetBalances()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
