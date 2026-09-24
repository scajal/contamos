<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\Activity;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Teams\GetActivity;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Teams\GetActivity.
 */

/**
 * Record an entry of a \App\Modules\Core\Identity\Models\Team
 * activity log directly, as the write path would.
 *
 * @param  array<string, mixed>  $attributes
 */
function recordGetActivityEntry(Team $team, array $attributes = []): Activity
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

it('shows every entry of the batch with its changes and references resolved to names', function (): void {
    [$user, $team] = mcpMember();
    $bank = Account::factory()->active()->for($team)->create(['name' => 'Bank']);
    $cash = Account::factory()->active()->for($team)->create(['name' => 'Cash']);
    $peso = Currency::factory()->active()->fiat()->create(['code' => 'ARS']);
    $transaction = Transaction::factory()->expense()->for($bank)->for($peso)->create(['description' => 'Groceries']);
    clearActivities();
    $batch = (string) Str::uuid();

    $first = recordGetActivityEntry($team, [
        'batch_uuid' => $batch,
        'subject_type' => $transaction->getMorphClass(),
        'subject_id' => $transaction->getKey(),
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->getKey(),
        'attribute_changes' => [
            'old' => ['account_id' => $bank->getKey(), 'amount' => '10.000000000000000000'],
            'attributes' => ['account_id' => $cash->getKey(), 'amount' => '12.000000000000000000'],
        ],
        'properties' => ['via' => 'mcp', 'client_id' => 'https://claude.ai/oauth/mcp-oauth-client-metadata', 'client_name' => 'claude.ai'],
    ]);
    recordGetActivityEntry($team, [
        'batch_uuid' => $batch,
        'event' => 'member_role_updated',
        'attribute_changes' => ['old' => ['role' => Role::Member->value], 'attributes' => ['role' => Role::Admin->value]],
        'properties' => ['via' => 'mcp', 'client_name' => 'claude.ai'],
    ]);

    ContamosServer::actingAs($user)
        ->tool(GetActivity::class, ['team' => $team->slug, 'activity' => $first->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('entries', 2)
            ->where('entries.0.id', $first->getKey())
            ->where('entries.0.subject_label', 'Groceries')
            ->where('entries.0.via', 'mcp')
            ->where('entries.0.client_name', 'claude.ai')
            ->where('entries.0.changes.0', [
                'field' => 'account_id',
                'old' => $bank->getKey(),
                'new' => $cash->getKey(),
                'old_label' => 'Bank',
                'new_label' => 'Cash',
            ])
            ->where('entries.0.changes.1.field', 'amount')
            ->where('entries.0.changes.1.new', '12.000000000000000000')
            ->where('entries.1.event', 'member_role_updated')
            ->where('entries.1.changes.0.old_label', Role::Member->getLabel())
            ->where('entries.1.changes.0.new_label', Role::Admin->getLabel())
            ->etc());
});

it('refuses an entry of another team', function (): void {
    [$user, $team] = mcpMember();
    $foreign = recordGetActivityEntry(Team::factory()->create());

    ContamosServer::actingAs($user)
        ->tool(GetActivity::class, ['team' => $team->slug, 'activity' => $foreign->getKey()])
        ->assertHasErrors(['Activity entry not found.']);
});

it('refuses a role without the permission to view activity', function (): void {
    [$user, $team] = mcpMember(Role::Member);
    $activity = recordGetActivityEntry($team);

    ContamosServer::actingAs($user)
        ->tool(GetActivity::class, ['team' => $team->slug, 'activity' => $activity->getKey()])
        ->assertHasErrors(['Your role on this team does not allow this.']);
});

it('refuses a team whose plan lacks the activity log', function (): void {
    [$user, $team] = mcpMember();
    Pennant::for($team)->deactivate(Feature::ViewActivity);
    $activity = recordGetActivityEntry($team);

    ContamosServer::actingAs($user)
        ->tool(GetActivity::class, ['team' => $team->slug, 'activity' => $activity->getKey()])
        ->assertHasErrors(["This team's plan does not allow this"]);
});

it('is a read-only tool', function (): void {
    expect(new GetActivity()->annotations())->toMatchArray(['readOnlyHint' => true]);
});
