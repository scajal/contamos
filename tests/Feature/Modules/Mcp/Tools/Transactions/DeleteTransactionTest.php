<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\DeleteTransaction;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\DeleteTransaction.
 */

/**
 * Create an income of 100.00 on an account of the team whose
 * balance already reflects it.
 *
 * @return array{0: \App\Modules\Core\Finance\Models\Team\Account, 1: \App\Modules\Core\Catalog\Models\Currency, 2: \App\Modules\Core\Finance\Models\Team\Transaction}
 */
function deleteTransactionToolIncome(Team $team): array
{
    $currency = Currency::factory()->active()->fiat()->create(['decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '100']);

    $transaction = Transaction::factory()->income()->for($account)->create([
        'amount' => '100',
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'occurred_at' => now(),
    ]);

    return [$account, $currency, $transaction];
}

it('deletes the transaction and reverts the balance', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency, $transaction] = deleteTransactionToolIncome($team);

    ContamosServer::actingAs($user)
        ->tool(DeleteTransaction::class, ['team' => $team->slug, 'transaction' => $transaction->getKey()])
        ->assertOk()
        ->assertStructuredContent(['deleted' => true, 'id' => $transaction->getKey()]);

    expect(Transaction::query()->count())->toBe(0)
        ->and(balanceOf($account, $currency))->toBe(0.0);
});

it('refuses a transaction that is part of a transfer', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency, $transaction] = deleteTransactionToolIncome($team);
    $transfer = Transfer::factory()->for($team)->create();
    $transaction->forceFill(['transfer_id' => $transfer->getKey(), 'type' => TransactionType::TransferIn, 'category' => null])->save();

    ContamosServer::actingAs($user)
        ->tool(DeleteTransaction::class, ['team' => $team->slug, 'transaction' => $transaction->getKey()])
        ->assertHasErrors(['part of a transfer']);

    expect(Transaction::query()->count())->toBe(1)
        ->and(balanceOf($account, $currency))->toBe(100.0);
});

it('reports a transaction of another team as not found', function (): void {
    [$user, $team] = mcpMember();
    [, , $foreign] = deleteTransactionToolIncome(Team::factory()->create());

    ContamosServer::actingAs($user)
        ->tool(DeleteTransaction::class, ['team' => $team->slug, 'transaction' => $foreign->getKey()])
        ->assertHasErrors(['Transaction not found.']);

    expect(Transaction::query()->count())->toBe(1);
});

it('is a destructive tool', function (): void {
    expect(new DeleteTransaction()->annotations())->toMatchArray(['destructiveHint' => true]);
});
