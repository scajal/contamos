<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Enums\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Identity\Models\Team;
use Filament\Support\Contracts\HasLabel;

/**
 * The role a membership carries. Every team has exactly one
 * Owner, the user who created it, and the role decides what
 * the member may do.
 *
 * @see \App\Modules\Core\Identity\Enums\Team\Permission
 */
enum Role: string implements HasLabel
{
    /**
     * Represents a member who helps manage the team, but
     * neither deletes it nor pays for it.
     */
    case Admin = 'admin';

    /**
     * Represents a member who only invites people and
     * leaves. Assigning it is a pro feature.
     */
    case Member = 'member';

    /**
     * Represents the user who created the team. There is
     * exactly one per team and it is never assignable.
     */
    case Owner = 'owner';

    /**
     * Get the roles a request may assign. The owner is
     * never among them.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member];
    }

    /**
     * Get the values of the roles a request may
     * assign.
     *
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::assignable());
    }

    /**
     * Get the roles the given team may hand out. Choosing a role
     * is a pro feature, so a team without it only has admins
     * besides its owner.
     *
     * @return array<int, self>
     */
    public static function assignableBy(Team $team): array
    {
        return Feature::AssignRoles->enabled($team) ? self::assignable() : [self::Admin];
    }

    /**
     * Get the label for the role.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => trans('Admin'),
            self::Member => trans('Member'),
            self::Owner => trans('Owner'),
        };
    }

    /**
     * Check whether the role grants the given
     * permission.
     */
    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Get the permissions the role grants.
     *
     * @return array<int, \App\Modules\Core\Identity\Enums\Team\Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // The owner may do everything.
            self::Owner => Permission::cases(),

            // An admin manages the team and its people, but
            // neither deletes it nor pays for it.
            self::Admin => [
                Permission::CancelInvitation,
                Permission::CreateInvitation,
                Permission::RemoveMember,
                Permission::UpdateMember,
                Permission::UpdateTeam,
            ],

            // A member only brings people in.
            self::Member => [
                Permission::CancelInvitation,
                Permission::CreateInvitation,
            ],
        };
    }
}
