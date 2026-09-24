<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Permission;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Identity\Enums\Team\Permission.
 */
it('translates the permission label', function (Permission $permission, string $label): void {
    App::setLocale('en');

    expect($permission->getLabel())->toBe($label);
})->with([
    'cancel invitation' => [Permission::CancelInvitation, 'Cancel invitations'],
    'create invitation' => [Permission::CreateInvitation, 'Invite people'],
    'delete team' => [Permission::DeleteTeam, 'Delete the team'],
    'manage billing' => [Permission::ManageBilling, 'Manage billing'],
    'manage notifications' => [Permission::ManageNotifications, 'Manage notifications'],
    'remove member' => [Permission::RemoveMember, 'Remove members'],
    'update member' => [Permission::UpdateMember, 'Change member roles'],
    'update team' => [Permission::UpdateTeam, 'Rename the team'],
]);
