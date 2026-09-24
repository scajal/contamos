<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\GetTransaction;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\GetTransaction.
 */

/**
 * Create an expense of 12.30 on an account of the team.
 */
function getTransactionToolExpense(Team $team): Transaction
{
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ZZA', 'decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency);

    return Transaction::factory()->expense()->for($account)->create([
        'amount' => '12.3',
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'occurred_at' => '2026-08-14 15:00:00',
    ]);
}

it('shows a transaction of the team with its local time', function (): void {
    [$user, $team] = mcpMember();
    $transaction = getTransactionToolExpense($team);

    ContamosServer::actingAs($user)
        ->tool(GetTransaction::class, ['team' => $team->slug, 'transaction' => $transaction->getKey(), 'timezone' => 'America/Montevideo'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transaction.id', $transaction->getKey())
            ->where('transaction.amount', '-12.30')
            ->where('transaction.currency', 'ZZA')
            ->where('transaction.occurred_at', '2026-08-14T15:00:00+00:00')
            ->where('transaction.occurred_at_local', '2026-08-14T12:00:00-03:00')
            ->etc());
});

it('reports a transaction of another team as not found', function (): void {
    [$user, $team] = mcpMember();
    $foreign = getTransactionToolExpense(Team::factory()->create());

    ContamosServer::actingAs($user)
        ->tool(GetTransaction::class, ['team' => $team->slug, 'transaction' => $foreign->getKey()])
        ->assertHasErrors(['Transaction not found.']);
});

it('is a read-only tool', function (): void {
    expect(new GetTransaction()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
