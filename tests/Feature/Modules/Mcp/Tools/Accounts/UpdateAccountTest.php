<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Accounts\UpdateAccount;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Accounts\UpdateAccount.
 */
it('renames the account and adds a currency with an opening balance', function (): void {
    [$user, $team] = mcpMember();
    $old = Currency::factory()->active()->fiat()->create(['code' => 'ZZA', 'decimal_places' => 2]);
    $new = Currency::factory()->active()->fiat()->create(['code' => 'ZZB', 'decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Old name']);
    $account->currencies()->attach($old);

    ContamosServer::actingAs($user)
        ->tool(UpdateAccount::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'name' => 'New name',
            'currencies' => ['ZZA', 'ZZB'],
            'opening_balances' => [['currency' => 'ZZB', 'amount' => '50', 'quote' => '1']],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('account.id', $account->getKey())
            ->where('account.name', 'New name')
            ->where('account.currencies.1.code', 'ZZB')
            ->where('account.currencies.1.balance', '50.00')
            ->etc());

    $account->refresh();

    expect($account->name)->toBe('New name')
        ->and($account->type)->toBe(AccountType::Bank)
        ->and($account->transactions()->sole()->currency_id)->toBe($new->getKey())
        ->and(balanceOf($account, $new))->toBe(50.0)
        ->and(balanceOf($account, $old))->toBe(0.0);
});

it('keeps the fields that are left out', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);
    $account = Account::factory()->cash()->active()->for($team)->create(['name' => 'Wallet']);
    $account->currencies()->attach($currency);

    ContamosServer::actingAs($user)
        ->tool(UpdateAccount::class, ['team' => $team->slug, 'account' => $account->getKey(), 'name' => 'Pocket'])
        ->assertOk();

    $account->refresh();

    expect($account->name)->toBe('Pocket')
        ->and($account->type)->toBe(AccountType::Cash)
        ->and($account->currencies()->pluck('code')->all())->toBe(['ZZA']);
});

it('rejects an opening balance for a currency the account already holds', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach($currency);

    ContamosServer::actingAs($user)
        ->tool(UpdateAccount::class, [
            'team' => $team->slug,
            'account' => $account->getKey(),
            'opening_balances' => [['currency' => 'ZZA', 'amount' => '10', 'quote' => '1']],
        ])
        ->assertHasErrors(['ZZA']);

    expect($account->transactions()->count())->toBe(0);
});

it('rejects an unknown account type', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);
    $account->currencies()->attach(Currency::factory()->active()->fiat()->create(['code' => 'ZZA']));

    ContamosServer::actingAs($user)
        ->tool(UpdateAccount::class, ['team' => $team->slug, 'account' => $account->getKey(), 'type' => 'savings'])
        ->assertHasErrors(['type']);

    expect($account->refresh()->type)->toBe(AccountType::Bank);
});

it('does not find an account of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = Account::factory()->bank()->active()->for(Team::factory())->create(['name' => 'Theirs']);

    ContamosServer::actingAs($user)
        ->tool(UpdateAccount::class, ['team' => $team->slug, 'account' => $foreign->getKey(), 'name' => 'Mine'])
        ->assertHasErrors(['Account not found.']);

    expect($foreign->refresh()->name)->toBe('Theirs');
});

it('is a destructive tool', function (): void {
    expect(new UpdateAccount()->annotations())->toMatchArray(['destructiveHint' => true]);
});
