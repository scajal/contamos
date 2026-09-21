/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\RecurringRule when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";
import type { Team } from "../../../identity/models/team";
import type { User } from "../../../identity/models/user";
import { Frequency } from "../../enums/recurring-rule/frequency";
import { Status } from "../../enums/recurring-rule/status";
import { TransactionType } from "../../enums/transaction/transaction-type";
import type { Account } from "./account";
import type { Tag } from "./tag";

export interface RecurringRule {
    id: string;
    account_id: string;
    amount: string;
    currency_id: string;
    decimal_places: number;
    description: string | null;
    failed_at: string | null;
    failure_reason: string | null;
    is_active: boolean;
    last_generated_due_date: string | null;
    type: TransactionType;
    frequency: Frequency;
    interval: number;
    starts_on: string;
    ends_on: string | null;
    time_of_day: string;
    timezone: string;
    team_id: string;
    user_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    /** Computed and appended by the controller for the listing. */
    next_due_date?: string | null;
    /** Computed and appended by the controller for the listing. */
    status?: Status;
    account?: Account;
    tags?: Array<Tag>;
    currency?: Currency;
    team?: Team;
    user?: User | null;
}
