/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\Transfer when serialized for Inertia.
 */
import type { Team } from "../../../identity/models/team";
import type { User } from "../../../identity/models/user";
import type { Tag } from "./tag";
import type { Transaction } from "./transaction";

export interface Transfer {
    id: string;
    description: string | null;
    destination_fee_id: string | null;
    destination_leg_id: string | null;
    origin_fee_id: string | null;
    origin_leg_id: string | null;
    occurred_at: string;
    team_id: string;
    user_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    tags?: Tag[];
    team?: Team;
    transactions?: Array<Transaction>;
    user?: User | null;
}
