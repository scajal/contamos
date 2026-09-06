/**
 * JSON shape of App\Modules\Core\Identity\Models\Team\TeamInvitation when serialized for Inertia.
 */
import type { Team } from "../team";
import type { User } from "../user";

export interface TeamInvitation {
    id: string;
    accepted_at: string | null;
    code: string;
    email: string;
    expires_at: string | null;
    invited_by: string;
    team_id: string;
    created_at: string | null;
    updated_at: string | null;
    inviter?: User;
    team?: Team;
}
