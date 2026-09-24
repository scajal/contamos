<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Tags\ListTags;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Tags\ListTags.
 */
it('lists only the team\'s tags, filtered by name and activity', function (): void {
    [$user, $team] = mcpMember();
    Tag::factory()->active()->for($team)->create(['name' => 'Vacation']);
    Tag::factory()->inactive()->for($team)->create(['name' => 'Old vacation']);
    Tag::factory()->active()->for($team)->create(['name' => 'Groceries']);
    Tag::factory()->active()->for(Team::factory())->create(['name' => 'Foreign vacation']);

    ContamosServer::actingAs($user)
        ->tool(ListTags::class, ['team' => $team->slug, 'search' => 'VACATION', 'is_active' => true])
        ->assertOk()
        ->assertSee('Vacation')
        ->assertDontSee(['Old vacation', 'Groceries', 'Foreign vacation']);
});

it('is a read-only tool', function (): void {
    expect(new ListTags()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
