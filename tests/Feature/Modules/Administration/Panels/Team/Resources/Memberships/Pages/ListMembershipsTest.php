<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Memberships\Pages\ListMemberships;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Memberships\Pages\ListMemberships.
 */
it('lists the members of the current tenant', function (): void {
    [$operator, $team] = actingOperatorOnTeamPanel();

    $membership = Membership::query()
        ->where($team->getForeignKey(), $team->getKey())
        ->where($operator->getForeignKey(), $operator->getKey())
        ->sole();

    Livewire::test(ListMemberships::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$membership]);
});

it('hides the members of another team', function (): void {
    [, $team] = actingOperator();
    $foreign = Team::factory()->create();
    memberOf($foreign, Role::Owner);

    $theirs = Membership::query()->where($foreign->getForeignKey(), $foreign->getKey())->sole();

    onTeamPanel($team);

    Livewire::test(ListMemberships::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$theirs]);
});
