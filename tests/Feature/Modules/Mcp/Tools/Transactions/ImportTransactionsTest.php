<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Notifications\Team\BudgetAlertNotification;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Transactions\ImportTransactions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Transactions\ImportTransactions.
 */

/**
 * Create an active account of the team holding one currency
 * with a zero balance.
 */
function importToolAccount(Team $team): Account
{
    $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Santander']);
    $account->currencies()->attach(
        Currency::factory()->active()->fiat()->create(['code' => 'ZZI', 'decimal_places' => 2]),
        ['balance' => '0'],
    );

    return $account;
}

/**
 * Build an expense row of a statement.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importRow(array $overrides = []): array
{
    return [
        'type' => 'expense',
        'amount' => '10.00',
        'category' => Category::casesFor(\App\Modules\Core\Finance\Enums\Transaction\TransactionType::Expense)[0]->value,
        'occurred_at' => '2026-08-14',
        'quote' => '1',
        'description' => 'Coffee',
        ...$overrides,
    ];
}

it('refuses more than 200 rows', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => array_fill(0, 201, importRow()),
        ])
        ->assertHasErrors(['At most 200 rows can be imported per call.']);

    expect(Transaction::query()->count())->toBe(0);
});

it('writes nothing when one row is invalid, keying the error by row', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [importRow(), importRow(['category' => 'not-a-category'])],
        ])
        ->assertHasErrors(['rows.1.category']);

    expect(Transaction::query()->count())->toBe(0);
});

it('writes nothing when a row names an unknown tag, naming the row', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [importRow(), importRow(['tags' => ['Nope']])],
        ])
        ->assertHasErrors(['rows.1: Unknown tag "Nope".']);

    expect(Transaction::query()->count())->toBe(0);
});

it('flags a row matching a recorded transaction as a suspected duplicate', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, ['team' => $team->slug, 'account' => $account->getKey(), 'rows' => [importRow()]])
        ->assertOk();

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, ['team' => $team->slug, 'account' => $account->getKey(), 'rows' => [importRow()]])
        ->assertHasErrors(['look like duplicates']);

    expect(Transaction::query()->count())->toBe(1);
});

it('flags rows repeating each other within the batch', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [importRow(), importRow()],
            'dry_run' => true,
        ])
        ->assertOk()
        ->assertSee('"duplicates":[{"row":1,"duplicate_of_row":0}]');
});

it('imports suspected duplicates once they are confirmed', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [importRow(), importRow()],
            'allow_duplicates' => true,
        ])
        ->assertOk();

    expect(Transaction::query()->count())->toBe(2)
        ->and(balanceOf($account, $account->currencies()->sole()))->toBe(-20.0);
});

it('writes nothing on a dry run and returns the summary', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [importRow(), importRow(['type' => 'income', 'amount' => '100', 'category' => Category::casesFor(\App\Modules\Core\Finance\Enums\Transaction\TransactionType::Income)[0]->value])],
            'dry_run' => true,
        ])
        ->assertOk()
        ->assertStructuredContent([
            'dry_run' => true,
            'rows' => 2,
            'totals' => [['currency' => 'ZZI', 'income' => '100.00', 'expense' => '10.00', 'rows' => 2]],
            'duplicates' => [],
        ]);

    expect(Transaction::query()->count())->toBe(0);
});

it('imports every row under one batch marked as made through MCP', function (): void {
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);
    clearActivities();
    Context::addHidden('mcp.client_id', 'https://claude.ai/oauth/mcp-oauth-client-metadata');

    $response = ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'rows' => [importRow(), importRow(['amount' => '5.50', 'occurred_at' => '2026-08-15'])],
        ])
        ->assertOk();

    $activities = activitiesOf($team, 'created');
    $importId = $activities->first()?->batch_uuid;

    expect(Transaction::query()->count())->toBe(2)
        ->and($importId)->not->toBeNull()
        ->and($activities->pluck('batch_uuid')->unique()->all())->toBe([$importId])
        ->and($activities->first()?->properties?->get('via'))->toBe('mcp')
        ->and(balanceOf($account, $account->currencies()->sole()))->toBe(-15.5);

    $response->assertSee('"import_id":"'.$importId.'"');
});

it('alerts once per budget after the whole import', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));
    [$user, $team] = mcpMember();
    $account = importToolAccount($team);
    $currency = $account->currencies()->sole();
    $food = BudgetPeriod::factory()->forCategory(Category::Food)->running()->for($team)->withWarningThreshold(50)->create([
        'amount' => '100',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    $transport = BudgetPeriod::factory()->forCategory(Category::Transport)->running()->for($team)->create([
        'amount' => '10',
        $currency->getForeignKey() => $currency->getKey(),
    ]);
    Notification::fake();

    ContamosServer::actingAs($user)
        ->tool(ImportTransactions::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'allow_duplicates' => true,
            'rows' => [
                ...array_fill(0, 6, importRow(['amount' => '20.00', 'category' => Category::Food->value, 'occurred_at' => '2026-09-14'])),
                importRow(['amount' => '15.00', 'category' => Category::Transport->value, 'occurred_at' => '2026-09-14']),
            ],
        ])
        ->assertOk();

    Notification::assertSentToTimes($user, BudgetAlertNotification::class, 2);
    Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->budget->is($food)
        && $notification->kind === BudgetAlertKind::Over
        && $notification->percentage === 120.0);
    Notification::assertSentTo($user, BudgetAlertNotification::class, fn (BudgetAlertNotification $notification): bool => $notification->budget->is($transport)
        && $notification->kind === BudgetAlertKind::Over);
});
