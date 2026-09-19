import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";
import { Role } from "@/types/app/modules/core/identity/enums/team/role";

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

    switch (role) {
        case "owner":
            return <Badge variant="default">{t("Owner")}</Badge>;
        case "member":
            return <Badge variant="ghost">{t("Member")}</Badge>;
        default:
            return <Badge variant="secondary">{t("Admin")}</Badge>;
    }
};
