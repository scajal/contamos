<?php

declare(strict_types=1);

use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Tags\SetTagActive;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Tags\SetTagActive.
 */
it('deactivates and reactivates a tag', function (): void {
    [$user, $team] = mcpMember();
    $tag = Tag::factory()->active()->for($team)->create();

    ContamosServer::actingAs($user)
        ->tool(SetTagActive::class, ['team' => $team->slug, 'tag' => $tag->getKey(), 'active' => false])
        ->assertOk();

    expect($tag->refresh()->is_active)->toBeFalse();

    ContamosServer::actingAs($user)
        ->tool(SetTagActive::class, ['team' => $team->slug, 'tag' => $tag->getKey(), 'active' => true])
        ->assertOk();

    expect($tag->refresh()->is_active)->toBeTrue();
});

it('does not find a tag of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = Tag::factory()->active()->for(Team::factory())->create();

    ContamosServer::actingAs($user)
        ->tool(SetTagActive::class, ['team' => $team->slug, 'tag' => $foreign->getKey(), 'active' => false])
        ->assertHasErrors(['Tag not found']);

    expect($foreign->refresh()->is_active)->toBeTrue();
});

it('is a destructive tool', function (): void {
    expect(new SetTagActive()->annotations())->toMatchArray(['destructiveHint' => true]);
});
