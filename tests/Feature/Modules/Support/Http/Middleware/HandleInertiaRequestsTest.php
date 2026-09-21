<?php

declare(strict_types=1);

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use App\Modules\Core\Identity\Models\User;
use Database\Factories\Modules\Core\Identity\Models\Team\TeamInvitationFactory;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Feature tests for \App\Modules\Support\Http\Middleware\HandleInertiaRequests.
 */

/**
 * The permission values the role grants.
 *
 * @return array<int, string>
 */
function permissionValues(Role $role): array
{
    return array_map(fn (Permission $permission): string => $permission->value, $role->permissions());
}

it('shares what the user may do on the current team of a pro team', function (Role $role): void {
    [$user, $team] = actingMember($role);
    subscribeToPro($team);

    $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions', permissionValues($role)));
})->with([
    'owner' => [Role::Owner],
    'admin' => [Role::Admin],
    'member' => [Role::Member],
]);

it('shares the admin permissions for a stored member on a free team', function (): void {
    [$user, $team] = actingMember(Role::Member);

    $this->get(route('identity.teams.members.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions', permissionValues(Role::Admin)));
});

it('shares no permissions for a user without a team', function (): void {
    actingAs(User::factory()->create());

    $this->get(route('identity.teams.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions', []));
});

it('shares an untouched setup checklist for a team with nothing', function (): void {
    [, $team] = actingMember();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('onboarding.has_account', false)
            ->where('onboarding.has_transaction', false)
            ->where('onboarding.has_member', false));
});

it('ticks the account step for an inactive account the dashboard hides', function (): void {
    [, $team] = actingMember();
    Account::factory()->bank()->inactive()->for($team)->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_accounts', false)
            ->where('onboarding.has_account', true));
});

it('ticks the transaction step for any transaction', function (): void {
    [, $team] = actingMember();
    $currency = Currency::factory()->active()->fiat()->create();
    $account = Account::factory()->cash()->inactive()->for($team)->create();

    Transaction::factory()->openingBalance()->for($account)->for($currency)->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('onboarding.has_transaction', true));
});

it('ticks the member step for an invitation in any state', function (TeamInvitationFactory $invitation): void {
    [, $team] = actingMember();
    $invitation->for($team)->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('onboarding.has_member', true));
})->with([
    'pending' => [fn (): TeamInvitationFactory => TeamInvitation::factory()],
    'accepted' => [fn (): TeamInvitationFactory => TeamInvitation::factory()->accepted()],
    'expired' => [fn (): TeamInvitationFactory => TeamInvitation::factory()->expired()],
]);

it('ticks the member step for a team of two with no invitation left', function (): void {
    [, $team] = actingMember();
    $team->users()->attach(User::factory()->create(), ['role' => Role::Member]);

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('onboarding.has_member', true));
});

it('leaves the setup checklist of another team alone', function (): void {
    [, $team] = actingMember();
    $foreign = Team::factory()->create();
    $currency = Currency::factory()->active()->fiat()->create();
    $account = Account::factory()->bank()->active()->for($foreign)->create();

    Transaction::factory()->income()->for($account)->for($currency)->create();
    TeamInvitation::factory()->for($foreign)->create();

    $this->get(route('finance.teams.dashboard.index', ['team' => $team->getRouteKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('onboarding.has_account', false)
            ->where('onboarding.has_transaction', false)
            ->where('onboarding.has_member', false));
});

it('shares no setup checklist for a user without a team', function (): void {
    actingAs(User::factory()->create());

    $this->get(route('identity.teams.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('onboarding', null));
});
