<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Teams\ListMembers;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Teams\ListMembers.
 */
it('lists only the team\'s members with their role', function (): void {
    [$user, $team] = mcpMember();
    $user->update(['name' => 'Ana Owner', 'email' => 'ana@example.com']);
    memberOf($team, Role::Member, User::factory()->create(['name' => 'Bruno Member', 'email' => 'bruno@example.com']));
    [$stranger] = mcpMember();
    $stranger->update(['name' => 'Aaron Stranger']);

    ContamosServer::actingAs($user)
        ->tool(ListMembers::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('members', 2)
            ->where('members.0.id', $user->getKey())
            ->where('members.0.name', 'Ana Owner')
            ->where('members.0.email', 'ana@example.com')
            ->where('members.0.role', Role::Owner->value)
            ->where('members.0.joined_at', fn (mixed $joinedAt): bool => is_string($joinedAt))
            ->where('members.1.name', 'Bruno Member')
            ->where('members.1.role', $team->effectiveRole(Role::Member)?->value)
            ->etc());
});

it('searches by part of a name or an email', function (): void {
    [$user, $team] = mcpMember();
    $user->update(['name' => 'Ana Owner', 'email' => 'ana@example.com']);
    memberOf($team, Role::Member, User::factory()->create(['name' => 'Bruno Member', 'email' => 'bruno@work.test']));

    ContamosServer::actingAs($user)
        ->tool(ListMembers::class, ['team' => $team->slug, 'search' => 'work.test'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('members', 1)
            ->where('members.0.name', 'Bruno Member')
            ->etc());

    ContamosServer::actingAs($user)
        ->tool(ListMembers::class, ['team' => $team->slug, 'search' => 'ana'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('members', 1)
            ->where('members.0.id', $user->getKey())
            ->where('members.0.name', 'Ana Owner')
            ->etc());
});

it('is a read-only tool', function (): void {
    expect(new ListMembers()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
