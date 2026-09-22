<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Administration\Resources\Teams\Pages\EditTeam;
use App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers\MembersRelationManager;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Membership;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers\MembersRelationManager.
 */
it('lists the members of the team', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    $member = memberOf($team, Role::Owner);

    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $team,
        'pageClass' => EditTeam::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertSee($member->email);
});

it('attaches a user to the team with a role', function (): void {
    actingOperator();
    $team = Team::factory()->create();
    memberOf($team, Role::Owner);
    $newcomer = User::factory()->create();

    Livewire::test(MembersRelationManager::class, [
        'ownerRecord' => $team,
        'pageClass' => EditTeam::class,
    ])
        ->callAction(TestAction::make('attach')->table(), [
            'recordId' => $newcomer->getKey(),
            'role' => Role::Member->value,
        ])
        ->assertHasNoActionErrors();

    $membership = Membership::query()
        ->where($team->getForeignKey(), $team->getKey())
        ->where($newcomer->getForeignKey(), $newcomer->getKey())
        ->sole();

    expect($membership->role)->toBe(Role::Member);
});
