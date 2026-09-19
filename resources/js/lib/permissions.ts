import { Permission } from "@/types/app/modules/core/identity/enums/team/permission";

/**
 * The permissions a team member may hold, mirroring
 * App\Modules\Core\Identity\Enums\Team\Permission so pages name
 * them instead of spelling the values out.
 */
export const PERMISSIONS = {
    CancelInvitation: "invitation:cancel",
    CreateInvitation: "invitation:create",
    DeleteTeam: "team:delete",
    ManageBilling: "billing:manage",
    RemoveMember: "member:remove",
    UpdateMember: "member:update",
    UpdateTeam: "team:update",
} as const satisfies Record<string, Permission>;
