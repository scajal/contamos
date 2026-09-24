<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Accounts\SetAccountActive;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Accounts\SetAccountActive.
 */
it('deactivates an account', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->active()->for($team)->create();
    $account->currencies()->attach(Currency::factory()->active()->fiat()->create(['code' => 'ZZA']));

    ContamosServer::actingAs($user)
        ->tool(SetAccountActive::class, ['team' => $team->slug, 'account' => $account->getKey(), 'active' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('account.is_active', false)->etc());

    expect($account->refresh()->is_active)->toBeFalse();
});

it('activates an account', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->inactive()->for($team)->create();

    ContamosServer::actingAs($user)
        ->tool(SetAccountActive::class, ['team' => $team->slug, 'account' => $account->getKey(), 'active' => true])
        ->assertOk();

    expect($account->refresh()->is_active)->toBeTrue();
});

it('refuses with the plan error when activating past the account limit', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->inactive()->for($team)->create();
    Pennant::for($team)->deactivate(Feature::AddAccounts);

    ContamosServer::actingAs($user)
        ->tool(SetAccountActive::class, ['team' => $team->slug, 'account' => $account->getKey(), 'active' => true])
        ->assertHasErrors(["This team's plan does not allow this"]);

    expect($account->refresh()->is_active)->toBeFalse();
});

it('deactivates past the account limit', function (): void {
    [$user, $team] = mcpMember();
    $account = Account::factory()->bank()->active()->for($team)->create();
    Pennant::for($team)->deactivate(Feature::AddAccounts);

    ContamosServer::actingAs($user)
        ->tool(SetAccountActive::class, ['team' => $team->slug, 'account' => $account->getKey(), 'active' => false])
        ->assertOk();

    expect($account->refresh()->is_active)->toBeFalse();
});

it('does not find an account of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = Account::factory()->bank()->active()->for(Team::factory())->create();

    ContamosServer::actingAs($user)
        ->tool(SetAccountActive::class, ['team' => $team->slug, 'account' => $foreign->getKey(), 'active' => false])
        ->assertHasErrors(['Account not found.']);

    expect($foreign->refresh()->is_active)->toBeTrue();
});

it('is a destructive tool', function (): void {
    expect(new SetAccountActive()->annotations())->toMatchArray(['destructiveHint' => true]);
});
