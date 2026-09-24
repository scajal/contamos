<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\UpdateTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\UpdateTransaction.
 */

/**
 * Create an expense of 40.00 on an account of the team whose
 * balance already reflects it.
 *
 * @return array{0: \App\Modules\Core\Finance\Models\Team\Account, 1: \App\Modules\Core\Catalog\Models\Currency, 2: \App\Modules\Core\Finance\Models\Team\Transaction}
 */
function updateTransactionToolExpense(Team $team): array
{
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ZZA', 'decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency, ['balance' => '-40']);

    $transaction = Transaction::factory()->expense()->for($account)->create([
        'amount' => '40',
        'category' => Category::Food,
        'currency_id' => $currency->getKey(),
        'decimal_places' => 2,
        'occurred_at' => '2026-08-14 12:00:00',
        'quote' => '1',
    ]);

    return [$account, $currency, $transaction];
}

it('changes only what is given and adjusts the balance', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency, $transaction] = updateTransactionToolExpense($team);
    $tag = Tag::factory()->for($team)->create(['name' => 'Trip', 'is_active' => true]);

    ContamosServer::actingAs($user)
        ->tool(UpdateTransaction::class, [
            'team' => $team->slug,
            'transaction' => $transaction->getKey(),
            'amount' => '55.25',
            'tags' => ['Trip'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transaction.amount', '-55.25')
            ->where('transaction.category', Category::Food->value)
            ->where('transaction.tags', [['id' => $tag->getKey(), 'name' => 'Trip']])
            ->etc());

    $transaction->refresh();

    expect(balanceOf($account, $currency))->toBe(-55.25)
        ->and($transaction->occurred_at->utc()->toIso8601String())->toBe('2026-08-14T12:00:00+00:00')
        ->and((float) $transaction->quote)->toBe(1.0);
});

it('flips the type and category and moves the balance', function (): void {
    [$user, $team] = mcpMember();
    [$account, $currency, $transaction] = updateTransactionToolExpense($team);

    ContamosServer::actingAs($user)
        ->tool(UpdateTransaction::class, [
            'team' => $team->slug,
            'transaction' => $transaction->getKey(),
            'type' => 'income',
            'category' => Category::Salary->value,
        ])
        ->assertOk();

    expect(balanceOf($account, $currency))->toBe(40.0)
        ->and($transaction->refresh()->type)->toBe(TransactionType::Income);
});

it('refuses a transaction that is part of a transfer', function (): void {
    [$user, $team] = mcpMember();
    [, , $transaction] = updateTransactionToolExpense($team);
    $transfer = Transfer::factory()->for($team)->create();
    $transaction->forceFill(['transfer_id' => $transfer->getKey(), 'type' => TransactionType::TransferOut, 'category' => null])->save();

    ContamosServer::actingAs($user)
        ->tool(UpdateTransaction::class, [
            'team' => $team->slug,
            'transaction' => $transaction->getKey(),
            'amount' => '1',
        ])
        ->assertHasErrors(['This transaction is part of a transfer; update the transfer instead']);

    expect((float) $transaction->refresh()->amount)->toBe(40.0);
});

it('reports a transaction of another team as not found', function (): void {
    [$user, $team] = mcpMember();
    [, , $foreign] = updateTransactionToolExpense(Team::factory()->create());

    ContamosServer::actingAs($user)
        ->tool(UpdateTransaction::class, [
            'team' => $team->slug,
            'transaction' => $foreign->getKey(),
            'amount' => '1',
        ])
        ->assertHasErrors(['Transaction not found.']);

    expect((float) $foreign->refresh()->amount)->toBe(40.0);
});

it('is a destructive tool', function (): void {
    expect(new UpdateTransaction()->annotations())->toMatchArray(['destructiveHint' => true]);
});

it('alerts when the edit pushes a budget over', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    [$user, $team] = mcpMember();
    [$account, $currency, $transaction] = updateTransactionToolExpense($team);
    BudgetPeriod::factory()->forCategory(Category::Food)->running()->for($team)->create([
        'amount' => '100',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    Notification::fake();

    ContamosServer::actingAs($user)
        ->tool(UpdateTransaction::class, [
            'team' => $team->slug,
            'transaction' => $transaction->getKey(),
            'amount' => '140',
            'occurred_at' => '2026-09-10',
            'quote' => '1',
        ])
        ->assertOk();

    Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->kind === BudgetAlertKind::Over);
});
