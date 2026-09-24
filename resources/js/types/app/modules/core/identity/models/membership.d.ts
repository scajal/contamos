/**
 * JSON shape of App\Modules\Core\Identity\Models\Membership when serialized for Inertia.
 */
import type { NotificationType } from "./../enums/team/notification-type";
import type { Role } from "./../enums/team/role";
import type { Team } from "./team";
import type { User } from "./user";

export interface Membership {
    id: number;
    team_id: string;
    user_id: string;
    role: Role;
    notification_preferences: Partial<Record<NotificationType, boolean>> | null;
    created_at: string | null;
    updated_at: string | null;
    team?: Team;
    user?: User;
}
