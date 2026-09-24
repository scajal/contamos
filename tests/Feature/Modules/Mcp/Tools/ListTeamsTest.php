<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\ListTeams;

/**
 * Feature tests for \App\Modules\Mcp\Tools\ListTeams.
 */
it('lists only the user\'s teams and which ones MCP can reach', function (): void {
    [$user, $free] = memberWithTeam();
    $free->update(['name' => 'A free team']);
    $pro = Team::factory()->grantedPlan(Plan::Pro)->create(['name' => 'B pro team']);
    memberOf($pro, Role::Member, $user);
    Team::factory()->grantedPlan(Plan::Pro)->create(['name' => 'C stranger team']);

    ContamosServer::actingAs($user)
        ->tool(ListTeams::class)
        ->assertOk()
        ->assertStructuredContent([
            'teams' => [
                ['slug' => $free->slug, 'name' => 'A free team', 'role' => 'owner', 'plan' => 'free', 'mcp_enabled' => true],
                ['slug' => $pro->slug, 'name' => 'B pro team', 'role' => 'member', 'plan' => 'pro', 'mcp_enabled' => true],
            ],
        ]);
});

it('leaves the team the user has open on the web alone', function (): void {
    [$user, $team] = memberWithTeam();
    memberOf(Team::factory()->create(), Role::Owner, $user);

    ContamosServer::actingAs($user)->tool(ListTeams::class)->assertOk();

    expect($user->refresh()->current_team_id)->toBe($team->getKey());
});

it('is a read-only tool', function (): void {
    [$user] = memberWithTeam();

    ContamosServer::actingAs($user)
        ->tools()
        ->assertRegistered(ListTeams::class);

    expect(new ListTeams()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
