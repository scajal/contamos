/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\Tag when serialized for Inertia.
 */
import type { Team } from "../../../identity/models/team";

export interface Tag {
    id: string;
    is_active: boolean;
    name: string;
    team_id: string;
    created_at: string | null;
    updated_at: string | null;
    team?: Team;
}
