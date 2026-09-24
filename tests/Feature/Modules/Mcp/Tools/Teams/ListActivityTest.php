<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Teams\ListActivity;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Teams\ListActivity.
 */

/**
 * Record an entry of a \App\Modules\Core\Identity\Models\Team
 * activity log directly, as the write path would.
 *
 * @param  array<string, mixed>  $attributes
 */
function recordListActivityEntry(Team $team, array $attributes = []): Activity
{
    /** @var \App\Modules\Core\Identity\Models\Team\Activity $activity */
    $activity = Activity::query()->create([
        'log_name' => 'default',
        'description' => 'updated',
        'event' => 'updated',
        'team_id' => $team->getKey(),
        'attribute_changes' => ['old' => [], 'attributes' => []],
        ...$attributes,
    ]);

    return $activity;
}

it('lists the team\'s entries newest first, marking the ones written over MCP', function (): void {
    [$user, $team] = mcpMember();
    clearActivities();
    $tag = Tag::factory()->for($team)->create(['name' => 'Food']);
    clearActivities();

    recordListActivityEntry($team, [
        'event' => 'created',
        'subject_type' => $tag->getMorphClass(),
        'subject_id' => $tag->getKey(),
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->getKey(),
        'created_at' => CarbonImmutable::parse('2026-08-01 10:00:00'),
    ]);
    recordListActivityEntry($team, [
        'event' => 'updated',
        'subject_type' => $tag->getMorphClass(),
        'subject_id' => $tag->getKey(),
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->getKey(),
        'properties' => ['via' => 'mcp', 'client_id' => 'https://claude.ai/oauth/mcp-oauth-client-metadata', 'client_name' => 'claude.ai'],
        'created_at' => CarbonImmutable::parse('2026-08-02 10:00:00'),
    ]);
    recordListActivityEntry(Team::factory()->create(), ['event' => 'foreign']);

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 2)
            ->where('items.0.event', 'updated')
            ->where('items.0.subject_type', ActivitySubject::Tag->value)
            ->where('items.0.subject_label', 'Food')
            ->where('items.0.causer', ['kind' => 'member', 'name' => $user->name])
            ->where('items.0.via', 'mcp')
            ->where('items.0.client_name', 'claude.ai')
            ->where('items.0.client_id', 'https://claude.ai/oauth/mcp-oauth-client-metadata')
            ->where('items.1.event', 'created')
            ->where('items.1.via', null)
            ->where('next_cursor', null)
            ->etc());
});

it('filters by subject, causer and dates', function (): void {
    [$user, $team] = mcpMember();
    $other = memberOf($team, Role::Admin, User::factory()->create(['email' => 'other@example.com']));
    clearActivities();

    recordListActivityEntry($team, ['event' => 'by_other', 'causer_type' => $other->getMorphClass(), 'causer_id' => $other->getKey(), 'created_at' => CarbonImmutable::parse('2026-08-05 10:00:00')]);
    recordListActivityEntry($team, ['event' => 'by_owner', 'causer_type' => $user->getMorphClass(), 'causer_id' => $user->getKey(), 'created_at' => CarbonImmutable::parse('2026-08-05 11:00:00')]);
    recordListActivityEntry($team, ['event' => 'too_early', 'causer_type' => $other->getMorphClass(), 'causer_id' => $other->getKey(), 'created_at' => CarbonImmutable::parse('2026-07-01 10:00:00')]);
    recordListActivityEntry($team, ['event' => 'tagged', 'subject_type' => new Tag()->getMorphClass(), 'subject_id' => 'x', 'created_at' => CarbonImmutable::parse('2026-08-05 12:00:00')]);

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug, 'causer' => 'other@example.com', 'from' => '2026-08-01', 'to' => '2026-08-31'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 1)
            ->where('items.0.event', 'by_other')
            ->etc());

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug, 'subject' => ActivitySubject::Tag->value])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 1)
            ->where('items.0.event', 'tagged')
            ->etc());
});

it('refuses a causer who is not a member of the team', function (): void {
    [$user, $team] = mcpMember();
    [$stranger] = mcpMember();

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug, 'causer' => $stranger->email])
        ->assertHasErrors(['No member of this team matches']);
});

it('pages through the entries with a cursor', function (): void {
    [$user, $team] = mcpMember();
    clearActivities();

    foreach (range(1, 51) as $minute) {
        recordListActivityEntry($team, ['event' => "entry_{$minute}", 'created_at' => CarbonImmutable::parse('2026-08-05 10:00:00')->addMinutes($minute)]);
    }

    $cursor = null;

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$cursor): AssertableJson {
            $cursor = $json->toArray()['next_cursor'] ?? null;

            return $json
                ->has('items', 50)
                ->where('items.0.event', 'entry_51')
                ->where('next_cursor', fn (mixed $next): bool => is_string($next))
                ->etc();
        });

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug, 'cursor' => $cursor])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('items', 1)
            ->where('items.0.event', 'entry_1')
            ->where('next_cursor', null)
            ->etc());
});

it('refuses a role without the permission to view activity', function (): void {
    [$user, $team] = mcpMember(Role::Member);

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug])
        ->assertHasErrors(['Your role on this team does not allow this.']);
});

it('refuses a team whose plan lacks the activity log', function (): void {
    [$user, $team] = mcpMember();
    Pennant::for($team)->deactivate(Feature::ViewActivity);

    ContamosServer::actingAs($user)
        ->tool(ListActivity::class, ['team' => $team->slug])
        ->assertHasErrors(["This team's plan does not allow this"]);
});

it('is a read-only tool', function (): void {
    expect(new ListActivity()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
