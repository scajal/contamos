/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\Transaction when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";
import type { Team } from "../../../identity/models/team";
import type { User } from "../../../identity/models/user";
import { TransactionType } from "../../enums/transaction/transaction-type";
import type { Account } from "./account";

export interface Transaction {
    id: string;
    account_id: string;
    amount: string;
    currency_id: string;
    decimal_places: number;
    description: string | null;
    exchange_rate: string;
    occurred_at: string;
    team_id: string;
    type: TransactionType;
    user_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    account?: Account;
    currency?: Currency;
    team?: Team;
    user?: User | null;
}
