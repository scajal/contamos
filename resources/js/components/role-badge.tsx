import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";
import { Role } from "@/types/app/modules/core/identity/enums/team/role";

/**
 * How each role reads on a badge. Keyed by role, so a new role is
 * a type error here instead of quietly rendering as an admin.
 */
const BADGES: Record<Role, { label: string; variant: "default" | "secondary" | "ghost" }> = {
    admin: { label: "Admin", variant: "secondary" },
    member: { label: "Member", variant: "ghost" },
    owner: { label: "Owner", variant: "default" },
};

/**
 * The role a member holds on the team, or the role an invitee
 * will hold once they accept. The server sends the effective
 * role, so a free team shows admins instead of members.
 */
export const RoleBadge = ({ role }: { role: Role }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const badge = BADGES[role];

    return <Badge variant={badge.variant}>{t(badge.label)}</Badge>;
};
