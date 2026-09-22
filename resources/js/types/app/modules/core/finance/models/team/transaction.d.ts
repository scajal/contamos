/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\Transaction when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";
import type { Team } from "../../../identity/models/team";
import type { User } from "../../../identity/models/user";
import { Category } from "../../enums/transaction/category";
import { TransactionType } from "../../enums/transaction/transaction-type";
import type { Account } from "./account";
import type { RecurringRule } from "./recurring-rule";
import type { Tag } from "./tag";
import type { Transfer } from "./transfer";

export interface Transaction {
    id: string;
    account_id: string;
    amount: string;
    category: Category | null;
    currency_id: string;
    decimal_places: number;
    description: string | null;
    due_date: string | null;
    occurred_at: string;
    quote: string;
    recurring_rule_id: string | null;
    team_id: string;
    type: TransactionType;
    transfer_id: string | null;
    user_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    account?: Account;
    currency?: Currency;
    recurringRule?: RecurringRule | null;
    tags?: Tag[];
    team?: Team;
    transfer?: Transfer | null;
    user?: User | null;
}
