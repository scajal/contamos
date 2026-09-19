<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Permission;
use App\Modules\Core\Identity\Enums\Team\Role;
use Illuminate\Support\Facades\App;

/**
 * Unit tests for \App\Modules\Core\Identity\Enums\Team\Role.
 */
it('grants the permissions of the role', function (Role $role, Permission $permission, bool $granted): void {
    expect($role->grants($permission))->toBe($granted);
})->with(function (): array {
    /** @var array<string, array{0: Role, 1: Permission, 2: bool}> $matrix */
    $matrix = [];

    /** @var array<string, array<string, bool>> $expected */
    $expected = [
        Permission::UpdateTeam->value => ['owner' => true, 'admin' => true, 'member' => false],
        Permission::DeleteTeam->value => ['owner' => true, 'admin' => false, 'member' => false],
        Permission::ManageBilling->value => ['owner' => true, 'admin' => false, 'member' => false],
        Permission::CreateInvitation->value => ['owner' => true, 'admin' => true, 'member' => true],
        Permission::CancelInvitation->value => ['owner' => true, 'admin' => true, 'member' => true],
        Permission::UpdateMember->value => ['owner' => true, 'admin' => true, 'member' => false],
        Permission::RemoveMember->value => ['owner' => true, 'admin' => true, 'member' => false],
    ];

    foreach ($expected as $permission => $roles) {
        foreach ($roles as $role => $granted) {
            $matrix["{$role} {$permission}"] = [Role::from($role), Permission::from($permission), $granted];
        }
    }

    return $matrix;
});

it('lists the permissions of the role', function (): void {
    expect(Role::Owner->permissions())->toBe(Permission::cases())
        ->and(Role::Admin->permissions())->toBe([
            Permission::CancelInvitation,
            Permission::CreateInvitation,
            Permission::RemoveMember,
            Permission::UpdateMember,
            Permission::UpdateTeam,
        ])
        ->and(Role::Member->permissions())->toBe([
            Permission::CancelInvitation,
            Permission::CreateInvitation,
        ]);
});

it('never lists the owner as assignable', function (): void {
    expect(Role::assignable())->toBe([Role::Admin, Role::Member]);
});

it('shapes the given roles as picker options', function (): void {
    App::setLocale('en');

    expect(Role::options(Role::assignable()))->toBe([
        ['label' => 'Admin', 'value' => 'admin'],
        ['label' => 'Member', 'value' => 'member'],
    ])->and(Role::options([]))->toBe([]);
});

it('translates the role label', function (Role $role, string $label): void {
    App::setLocale('en');

    expect($role->getLabel())->toBe($label);
})->with([
    'admin' => [Role::Admin, 'Admin'],
    'member' => [Role::Member, 'Member'],
    'owner' => [Role::Owner, 'Owner'],
]);
