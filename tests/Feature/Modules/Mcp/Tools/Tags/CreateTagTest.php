<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Tags\CreateTag;
use Carbon\CarbonImmutable;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Tags\CreateTag.
 */
it('creates a tag on the team', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(CreateTag::class, ['team' => $team->slug, 'name' => 'Vacation'])
        ->assertOk()
        ->assertSee('Vacation');

    expect($team->tags()->where('name', 'Vacation')->exists())->toBeTrue();
});

it('opens a budget from this month for each currency code given', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);

    ContamosServer::actingAs($user)
        ->tool(CreateTag::class, ['team' => $team->slug, 'name' => 'Vacation', 'budgets' => ['usd' => '500']])
        ->assertOk();

    /** @var \App\Modules\Core\Finance\Models\Team\Tag $tag */
    $tag = $team->tags()->where('name', 'Vacation')->firstOrFail();
    $period = $tag->budgetPeriods()->sole();

    expect((float) $period->amount)->toBe(500.0)
        ->and($period->currency_id)->toBe($currency->getKey())
        ->and($period->from_month->toDateString())->toBe(CarbonImmutable::now()->startOfMonth()->toDateString())
        ->and($period->until_month)->toBeNull();
});

it('refuses budgets when the plan has no budgets', function (): void {
    [$user, $team] = mcpMember();
    Pennant::for($team)->deactivate(Feature::ManageBudgets);

    ContamosServer::actingAs($user)
        ->tool(CreateTag::class, ['team' => $team->slug, 'name' => 'Vacation', 'budgets' => ['USD' => '500']])
        ->assertHasErrors(['Budgets']);

    expect($team->tags()->count())->toBe(0);
});

it('refuses a currency the team does not hold', function (): void {
    [$user, $team] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(CreateTag::class, ['team' => $team->slug, 'name' => 'Vacation', 'budgets' => ['EUR' => '500']])
        ->assertHasErrors(['does not hold EUR']);

    expect($team->tags()->count())->toBe(0);
});

it('refuses a name another tag of the team uses', function (): void {
    [$user, $team] = mcpMember();
    Tag::factory()->for($team)->create(['name' => 'Vacation']);

    ContamosServer::actingAs($user)
        ->tool(CreateTag::class, ['team' => $team->slug, 'name' => 'vacation'])
        ->assertHasErrors(['name']);

    expect($team->tags()->count())->toBe(1);
});
