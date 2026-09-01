/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\Account when serialized for Inertia.
 */
import { Currency } from "../../../catalog/models/currency";
import type { Team } from "../../../identity/models/team";
import type { AccountType } from "../../enums/account/account-type";
import type { Transaction } from "./transaction";

export interface Account {
    id: string;
    currencies?: Array<Currency>;
    name: string;
    team_id: string;
    type: AccountType;
    created_at: string | null;
    updated_at: string | null;
    team?: Team;
    transactions?: Array<Transaction>;
}
