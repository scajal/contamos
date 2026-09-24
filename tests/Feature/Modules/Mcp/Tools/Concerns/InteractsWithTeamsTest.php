<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Servers\ContamosServer;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Support\Helpers\TeamActivity;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Context;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Pennant\Feature as Pennant;

/**
 * Feature tests for \App\Modules\Mcp\Tools\Concerns\InteractsWithTeams.
 */

/**
 * A team-scoped tool that reports what the concern resolved and
 * writes an activity entry, standing in for the real tools.
 */
#[Name('probe_team')]
class ProbeTeamTool extends Tool
{
    use InteractsWithTeams;

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request): ResponseFactory {
            if ($request->get('permission') === true) {
                $this->ensurePermission($user, $team, Permission::ViewActivity);
            }

            if ($request->get('feature') === true) {
                $this->ensureFeature($team, Feature::AddAccounts);
            }

            TeamActivity::record($team, 'probed');

            return Response::structured([
                'team' => $team->slug,
                'scope_team' => Pennant::active(Feature::UseMcp),
            ]);
        });
    }
}

/**
 * The Contamos server with the probe registered next to the real
 * tools.
 */
class ProbeServer extends ContamosServer
{
    protected function boot(): void
    {
        parent::boot();

        $this->tools[] = ProbeTeamTool::class;
    }
}

it('works on a pro team the user belongs to', function (): void {
    [$user, $team] = mcpMember();

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(['team' => $team->slug, 'scope_team' => true]);
});

it('refuses an unknown team and a team the user is not in alike', function (): void {
    [$user] = mcpMember();
    $stranger = Team::factory()->create();
    subscribeToPro($stranger);

    $message = 'Team not found. Call `list_teams` to see the slugs of the teams you belong to.';

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => 'no-such-team'])
        ->assertHasErrors([$message]);

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $stranger->slug])
        ->assertHasErrors([$message]);
});

it('reaches a free team', function (): void {
    [$user, $team] = memberWithTeam();

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $team->slug])
        ->assertHasNoErrors();
});

it('refuses a team whose plan leaves assistants out, with the upgrade link', function (): void {
    [$user, $team] = memberWithTeam();
    Pennant::for($team)->deactivate(Feature::UseMcp);

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $team->slug])
        ->assertHasErrors([
            "This team's plan does not include AI assistant access. Upgrade to Pro: ".route('billing.teams.subscriptions.upgrade', ['team' => $team->slug, 'feature' => 'mcp:use']),
        ]);
});

it('refuses a member without the permission', function (): void {
    [$user, $team] = mcpMember(Role::Member);

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $team->slug, 'permission' => true])
        ->assertHasErrors(['Your role on this team does not allow this.']);
});

it('refuses a feature the plan lacks', function (): void {
    [$user, $team] = mcpMember();
    Pennant::for($team)->deactivate(Feature::AddAccounts);

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $team->slug, 'feature' => true])
        ->assertHasErrors(["This team's plan does not allow this (Accounts per team). Upgrade to Pro: ".route('billing.teams.subscriptions.upgrade', ['team' => $team->slug, 'feature' => 'accounts:create'])]);
});

it('leaves the team the user has open on the web alone', function (): void {
    [$user, $open] = memberWithTeam();
    $other = Team::factory()->create();
    subscribeToPro($other);
    memberOf($other, Role::Owner, $user);

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $other->slug])
        ->assertOk();

    expect($user->refresh()->current_team_id)->toBe($open->getKey());
});

it('marks what an MCP call writes to the activity log with the assistant', function (): void {
    [$user, $team] = mcpMember();
    clearActivities();
    Context::addHidden('mcp.client_id', 'https://claude.ai/oauth/mcp-oauth-client-metadata');

    ProbeServer::actingAs($user)
        ->tool(ProbeTeamTool::class, ['team' => $team->slug])
        ->assertOk();

    expect(activitiesOf($team, 'probed')->sole()->properties?->all())->toMatchArray([
        'via' => 'mcp',
        'client_id' => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
        'client_name' => 'claude.ai',
    ]);
});

it('leaves what the web writes to the activity log unmarked', function (): void {
    [$user, $team] = actingMember();
    clearActivities();

    TeamActivity::record($team, 'probed');

    expect(activitiesOf($team, 'probed')->sole()->properties?->all() ?? [])->not->toHaveKey('via');
});
