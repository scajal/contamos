<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Accounts\ListAccounts;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Accounts\ListAccounts.
 */
it('lists the team\'s accounts with their currencies and balances', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'ZZA', 'decimal_places' => 2]);
    $account = Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking']);
    $account->currencies()->attach($currency, ['balance' => '1234.5']);
    Account::factory()->bank()->active()->for(Team::factory())->create(['name' => 'Stranger']);

    ContamosServer::actingAs($user)
        ->tool(ListAccounts::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent([
            'items' => [[
                'id' => $account->getKey(),
                'name' => 'Checking',
                'type' => 'bank',
                'is_active' => true,
                'currencies' => [['code' => 'ZZA', 'name' => $currency->name, 'balance' => '1234.50']],
            ]],
            'next_cursor' => null,
        ]);
});

it('filters by type, currency, name and active state', function (array $filters, string $expected): void {
    [$user, $team] = mcpMember();
    $fiat = Currency::factory()->active()->fiat()->create(['code' => 'ZZA']);
    $crypto = Currency::factory()->active()->crypto()->create(['code' => 'ZZC']);
    Account::factory()->bank()->active()->for($team)->create(['name' => 'Checking'])->currencies()->attach($fiat);
    Account::factory()->crypto()->active()->for($team)->create(['name' => 'Cold wallet'])->currencies()->attach($crypto);
    Account::factory()->cash()->inactive()->for($team)->create(['name' => 'Old cash'])->currencies()->attach($fiat);

    ContamosServer::actingAs($user)
        ->tool(ListAccounts::class, ['team' => $team->slug, ...$filters])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 1)
            ->where('items.0.name', $expected)
            ->etc());
})->with([
    'type' => [['type' => 'crypto'], 'Cold wallet'],
    'currency' => [['currency' => 'zzc'], 'Cold wallet'],
    'search' => [['search' => 'check'], 'Checking'],
    'inactive' => [['is_active' => false], 'Old cash'],
    'active fiat' => [['is_active' => true, 'currency' => 'ZZA'], 'Checking'],
]);

it('rejects an unknown type filter', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(ListAccounts::class, ['team' => $team->slug, 'type' => 'savings'])
        ->assertHasErrors(['type']);
});

it('is a read-only tool', function (): void {
    expect(new ListAccounts()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
