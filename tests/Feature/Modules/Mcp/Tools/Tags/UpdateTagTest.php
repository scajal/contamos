<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Tags\UpdateTag;
use Carbon\CarbonImmutable;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Tags\UpdateTag.
 */
it('renames a tag found by name', function (): void {
    [$user, $team] = mcpMember();
    $tag = Tag::factory()->for($team)->create(['name' => 'Vacation']);

    ContamosServer::actingAs($user)
        ->tool(UpdateTag::class, ['team' => $team->slug, 'tag' => 'vacation', 'name' => 'Holidays'])
        ->assertOk()
        ->assertSee('Holidays');

    expect($tag->refresh()->name)->toBe('Holidays');
});

it('moves the running budget and keeps past months on the old amount', function (): void {
    [$user, $team] = mcpMember();
    $currency = Currency::factory()->active()->fiat()->create(['code' => 'USD', 'decimal_places' => 2]);
    Account::factory()->active()->for($team)->create()->currencies()->attach($currency, ['balance' => '0']);
    $tag = Tag::factory()->for($team)->create(['name' => 'Vacation']);
    $old = BudgetPeriod::factory()->forTag($tag)->for($currency)->create([
        'amount' => '300',
        'from_month' => CarbonImmutable::now()->startOfMonth()->subMonths(2),
        'until_month' => null,
    ]);

    ContamosServer::actingAs($user)
        ->tool(UpdateTag::class, ['team' => $team->slug, 'tag' => $tag->getKey(), 'budgets' => ['USD' => '450']])
        ->assertOk();

    $running = $tag->budgetPeriods()->covering(CarbonImmutable::now())->sole();

    expect($tag->refresh()->name)->toBe('Vacation')
        ->and((float) $running->amount)->toBe(450.0)
        ->and($old->refresh()->until_month?->toDateString())->toBe(CarbonImmutable::now()->startOfMonth()->subMonth()->toDateString());
});

it('does not find a tag of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = Tag::factory()->for(Team::factory())->create(['name' => 'Vacation']);

    ContamosServer::actingAs($user)
        ->tool(UpdateTag::class, ['team' => $team->slug, 'tag' => $foreign->getKey(), 'name' => 'Mine'])
        ->assertHasErrors(['Tag not found']);

    expect($foreign->refresh()->name)->toBe('Vacation');
});

it('is a destructive tool', function (): void {
    expect(new UpdateTag()->annotations())->toMatchArray(['destructiveHint' => true]);
});
