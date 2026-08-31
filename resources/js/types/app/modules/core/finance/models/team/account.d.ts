/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\Account when serialized for Inertia.
 */
import type { AccountType } from "../../enums/account/account-type";
import type { Team } from "../../../identity/models/team";

export interface Account {
    id: string;
    name: string;
    team_id: string;
    type: AccountType;
    created_at: string | null;
    updated_at: string | null;
    team?: Team;
}
