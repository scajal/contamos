<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Accounts\CreateAccount;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Accounts\CreateAccount.
 */
it('creates an account with its currencies and opening balances', function (): void {
    [$user, $team] = mcpMember();
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ZZA', 'decimal_places' => 2]);
    $dollar = Currency::factory()->active()->fiat()->create(['code' => 'ZZB', 'decimal_places' => 2]);
    Quote::factory()->for($peso)->create(['observed_at' => '2024-06-01 00:00:00', 'rate' => '40']);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, [
            'team' => $team->slug,
            'name' => 'Checking',
            'type' => 'bank',
            'currencies' => ['zza', 'ZZB'],
            'opening_balances' => [
                ['currency' => 'ZZA', 'amount' => '100.50'],
                ['currency' => 'ZZB', 'amount' => '25', 'quote' => '1'],
            ],
            'occurred_at' => '2024-06-01',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('account.name', 'Checking')
            ->where('account.type', 'bank')
            ->where('account.is_active', true)
            ->where('account.currencies', [
                ['code' => 'ZZA', 'name' => $peso->name, 'balance' => '100.50'],
                ['code' => 'ZZB', 'name' => $dollar->name, 'balance' => '25.00'],
            ])
            ->etc());

    $account = $team->accounts()->sole();
    $opening = $account->transactions()->where('currency_id', $peso->getKey())->sole();

    expect($account->type)->toBe(AccountType::Bank)
        ->and($account->transactions()->count())->toBe(2)
        ->and($opening->type)->toBe(TransactionType::OpeningBalance)
        ->and((float) $opening->quote)->toBe(40.0)
        ->and($opening->occurred_at->toIso8601String())->toBe('2024-06-01T12:00:00+00:00')
        ->and($opening->user_id)->toBe($user->getKey())
        ->and(balanceOf($account, $peso))->toBe(100.5)
        ->and(balanceOf($account, $dollar))->toBe(25.0);
});

it('rejects an unknown account type', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, ['team' => $team->slug, 'name' => 'Savings', 'type' => 'savings', 'currencies' => ['ZZA']])
        ->assertHasErrors(['type']);

    expect(Account::query()->count())->toBe(0);
});

it('rejects an unknown currency code', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, ['team' => $team->slug, 'name' => 'Checking', 'type' => 'bank', 'currencies' => ['NOPE']])
        ->assertHasErrors(['NOPE']);

    expect(Account::query()->count())->toBe(0);
});

it('rejects currencies that are not a list', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, ['team' => $team->slug, 'name' => 'Checking', 'type' => 'bank', 'currencies' => 'ZZA'])
        ->assertHasErrors(['The currencies must be a list of ISO codes.']);

    expect(Account::query()->count())->toBe(0);
});

it('rejects opening balances that are not a list', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, [
            'team' => $team->slug,
            'name' => 'Checking',
            'type' => 'bank',
            'currencies' => ['ZZA'],
            'opening_balances' => '10',
        ])
        ->assertHasErrors(['The opening balances must be a list of {currency, amount, quote} objects.']);

    expect(Account::query()->count())->toBe(0);
});

it('rejects a cryptocurrency on a bank account', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->crypto()->create(['code' => 'ZZC']);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, ['team' => $team->slug, 'name' => 'Checking', 'type' => 'bank', 'currencies' => ['ZZC']])
        ->assertHasErrors(['ZZC']);

    expect(Account::query()->count())->toBe(0);
});

it('rejects an opening balance with no exchange rate for the date', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->fiat()->create(['code' => 'ZZA', 'decimal_places' => 2]);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, [
            'team' => $team->slug,
            'name' => 'Checking',
            'type' => 'bank',
            'currencies' => ['ZZA'],
            'opening_balances' => [['currency' => 'ZZA', 'amount' => '10']],
        ])
        ->assertHasErrors(['No exchange rate exists for ZZA']);

    expect(Account::query()->count())->toBe(0);
});

it('rejects an opening balance for a currency the account does not hold', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);
    Currency::factory()->active()->fiat()->create(['code' => 'ZZB']);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, [
            'team' => $team->slug,
            'name' => 'Checking',
            'type' => 'bank',
            'currencies' => ['ZZA'],
            'opening_balances' => [['currency' => 'ZZB', 'amount' => '10', 'quote' => '1']],
        ])
        ->assertHasErrors(['ZZB']);

    expect(Account::query()->count())->toBe(0);
});

it('refuses with the plan error when the account limit is reached', function (): void {
    [$user, $team] = mcpMember();
    Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);
    Pennant::for($team)->deactivate(Feature::AddAccounts);

    ContamosServer::actingAs($user)
        ->tool(CreateAccount::class, ['team' => $team->slug, 'name' => 'Checking', 'type' => 'bank', 'currencies' => ['ZZA']])
        ->assertHasErrors(["This team's plan does not allow this", 'feature=accounts']);

    expect(Account::query()->count())->toBe(0);
});
