<?php

declare(strict_types=1);

use App\Modules\Administration\Panels\Team\Resources\Memberships\Pages\EditMembership;
use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Membership;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

/**
 * Feature tests for \App\Modules\Administration\Panels\Team\Resources\Memberships\Pages\EditMembership.
 */
it('updates the role the member carries', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $member = memberOf($team, Role::Member);
    $membership = Membership::query()
        ->where($team->getForeignKey(), $team->getKey())
        ->where($member->getForeignKey(), $member->getKey())
        ->sole();

    Livewire::test(EditMembership::class, ['record' => $membership->getKey()])
        ->fillForm(['role' => Role::Admin->value])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($membership->fresh()?->role)->toBe(Role::Admin);
});

it('removes the member from the team', function (): void {
    [, $team] = actingOperatorOnTeamPanel();
    $member = memberOf($team, Role::Member);
    $membership = Membership::query()
        ->where($team->getForeignKey(), $team->getKey())
        ->where($member->getForeignKey(), $member->getKey())
        ->sole();

    Livewire::test(EditMembership::class, ['record' => $membership->getKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    expect(Membership::query()->find($membership->getKey()))->toBeNull();
});
