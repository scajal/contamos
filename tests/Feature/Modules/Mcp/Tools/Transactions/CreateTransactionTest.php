<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\CreateTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\CreateTransaction.
 */

/**
 * Create an active account of the team holding one currency
 * per code, each with a zero balance.
 *
 * @param  array<int, string>  $codes
 */
function createTransactionToolAccount(Team $team, array $codes = ['ZZA']): Account
{
    $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);

    foreach ($codes as $code) {
        $account->currencies()->attach(
            Currency::factory()->active()->fiat()->create(['code' => $code, 'decimal_places' => 2]),
            ['balance' => '0'],
        );
    }

    return $account;
}

it('records an income, moves the balance up and attaches the tags', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);
    $currency = $account->currencies()->sole();
    $tag = Tag::factory()->for($team)->create(['name' => 'Work', 'is_active' => true]);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'income',
            'account' => $account->getKey(),
            'amount' => '1250.50',
            'category' => Category::Salary->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
            'tags' => ['work'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transaction.type', 'income')
            ->where('transaction.amount', '1250.50')
            ->where('transaction.currency', 'ZZA')
            ->where('transaction.account.id', $account->getKey())
            ->where('transaction.tags', [['id' => $tag->getKey(), 'name' => 'Work']])
            ->where('transaction.transfer_id', null)
            ->where('transaction.recurring_rule_id', null)
            ->where('recurring_rule', null)
            ->etc());

    $transaction = Transaction::query()->sole();

    expect(balanceOf($account, $currency))->toBe(1250.5)
        ->and($transaction->user_id)->toBe($user->getKey())
        ->and($transaction->tags->pluck('id')->all())->toBe([$tag->getKey()]);
});

it('records an expense and moves the balance down', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => 40,
            'category' => Category::Food->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transaction.amount', '-40.00')
            ->etc());

    expect(balanceOf($account, $account->currencies()->sole()))->toBe(-40.0)
        ->and(Transaction::query()->sole()->type)->toBe(TransactionType::Expense);
});

it('infers the currency of a single-currency account and stores a date at noon', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '10',
            'category' => Category::Food->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
        ])
        ->assertOk();

    $transaction = Transaction::query()->sole();

    expect($transaction->currency_id)->toBe($account->currencies()->sole()->getKey())
        ->and($transaction->occurred_at->utc()->toIso8601String())->toBe('2026-08-14T12:00:00+00:00');
});

it('asks for the currency of an account holding several', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team, ['ZZA', 'ZZB']);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '10',
            'category' => Category::Food->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
        ])
        ->assertHasErrors(['ZZA, ZZB']);

    expect(Transaction::query()->count())->toBe(0);
});

it('defaults the quote from the rate recorded for the date', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);
    $currency = $account->currencies()->sole();
    Quote::factory()->for($currency)->create(['observed_at' => '2026-08-01 00:00:00', 'rate' => '40']);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '10',
            'category' => Category::Food->value,
            'occurred_at' => '2026-08-14',
        ])
        ->assertOk();

    expect((float) Transaction::query()->sole()->quote)->toBe(40.0);
});

it('rejects a category that belongs to the other type', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '10',
            'category' => Category::Salary->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
        ])
        ->assertHasErrors(['category']);

    expect(Transaction::query()->count())->toBe(0);
});

it('creates the recurring rule and its first occurrence when it is due', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);
    $tag = Tag::factory()->for($team)->create(['name' => 'Home', 'is_active' => true]);
    $today = CarbonImmutable::now('America/Montevideo');

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '500',
            'category' => Category::Housing->value,
            'occurred_at' => $today->format('Y-m-d').' 00:05',
            'timezone' => 'America/Montevideo',
            'quote' => '1',
            'tags' => [$tag->getKey()],
            'repeat' => true,
            'recurring_frequency' => 'monthly',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('recurring_rule.frequency', 'monthly')
            ->where('recurring_rule.interval', 1)
            ->where('recurring_rule.starts_on', $today->toDateString())
            ->where('recurring_rule.time_of_day', '00:05:00')
            ->where('transaction.amount', '-500.00')
            ->etc());

    $rule = RecurringRule::query()->sole();
    $transaction = Transaction::query()->sole();

    expect($rule->timezone)->toBe('America/Montevideo')
        ->and($rule->user_id)->toBe($user->getKey())
        ->and($rule->tags->pluck('id')->all())->toBe([$tag->getKey()])
        ->and($rule->last_generated_due_date?->toDateString())->toBe($today->toDateString())
        ->and($transaction->recurring_rule_id)->toBe($rule->getKey())
        ->and($transaction->due_date?->toDateString())->toBe($today->toDateString())
        ->and(balanceOf($account, $account->currencies()->sole()))->toBe(-500.0);
});

it('creates only the recurring rule when it starts later', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);
    $later = CarbonImmutable::now('UTC')->addMonth();

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '500',
            'category' => Category::Housing->value,
            'occurred_at' => $later->toDateString(),
            'timezone' => 'UTC',
            'quote' => '1',
            'repeat' => true,
            'recurring_frequency' => 'monthly',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('transaction', null)
            ->where('recurring_rule.starts_on', $later->toDateString())
            ->etc());

    expect(RecurringRule::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(balanceOf($account, $account->currencies()->sole()))->toBe(0.0);
});

it('requires a timezone to repeat', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '500',
            'category' => Category::Housing->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
            'repeat' => true,
            'recurring_frequency' => 'monthly',
        ])
        ->assertHasErrors(['timezone']);

    expect(RecurringRule::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('refuses an account of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = createTransactionToolAccount(Team::factory()->create());

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $foreign->getKey(),
            'amount' => '10',
            'category' => Category::Food->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
        ])
        ->assertHasErrors(['Account not found.']);

    expect(Transaction::query()->count())->toBe(0);
});

it('refuses an inactive account', function (): void {
    [$user, $team] = mcpMember();
    $account = createTransactionToolAccount($team);
    $account->deactivate();

    ContamosServer::actingAs($user)
        ->tool(CreateTransaction::class, [
            'team' => $team->slug,
            'type' => 'expense',
            'account' => $account->getKey(),
            'amount' => '10',
            'category' => Category::Food->value,
            'occurred_at' => '2026-08-14',
            'quote' => '1',
        ])
        ->assertHasErrors(['is inactive']);

    expect(Transaction::query()->count())->toBe(0);
});
