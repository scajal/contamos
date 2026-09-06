/**
 * JSON shape of App\Modules\Core\Identity\Models\User when serialized for Inertia.
 * Hidden attributes (`external_id`, `remember_token`) are omitted.
 */
import type { Membership } from "./membership";
import type { Team } from "./team";

export interface User {
    id: string;
    avatar: string | null;
    current_team_id: string | null;
    current_team: Team | null;
    email: string;
    email_verified_at: string | null;
    is_active: boolean;
    name: string;
    created_at: string | null;
    updated_at: string | null;
    membership?: Membership | null;
    teams?: Team[];
}
