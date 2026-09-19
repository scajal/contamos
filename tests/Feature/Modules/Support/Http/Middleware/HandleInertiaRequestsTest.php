<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\User;
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
