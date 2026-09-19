<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Enums\Team;

use Filament\Support\Contracts\HasLabel;

/**
 * The things a member may do on a team. Each role grants a
 * fixed set of them.
 *
 * @see \App\Modules\Core\Identity\Enums\Team\Role
 */
enum Permission: string implements HasLabel
{
    /**
     * Represents cancelling and resending a pending
     * invitation.
     */
    case CancelInvitation = 'invitation:cancel';

    /**
     * Represents inviting somebody to the team.
     */
    case CreateInvitation = 'invitation:create';

    /**
     * Represents deleting the team.
     */
    case DeleteTeam = 'team:delete';

    /**
     * Represents managing the team's subscription, from
     * subscribing to downloading invoices.
     */
    case ManageBilling = 'billing:manage';

    /**
     * Represents removing another member from the team.
     */
    case RemoveMember = 'member:remove';

    /**
     * Represents changing another member's role.
     */
    case UpdateMember = 'member:update';

    /**
     * Represents renaming the team and changing
     * its handle.
     */
    case UpdateTeam = 'team:update';

    /**
     * Get the label for the permission.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CancelInvitation => trans('Cancel invitations'),
            self::CreateInvitation => trans('Invite people'),
            self::DeleteTeam => trans('Delete the team'),
            self::ManageBilling => trans('Manage billing'),
            self::RemoveMember => trans('Remove members'),
            self::UpdateMember => trans('Change member roles'),
            self::UpdateTeam => trans('Rename the team'),
        };
    }
}
