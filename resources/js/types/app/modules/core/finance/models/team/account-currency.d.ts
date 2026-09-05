/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\AccountCurrency when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";
import type { Account } from "./account";

export interface AccountCurrency {
    id: string;
    account_id: string;
    balance: string;
    currency_id: string;
    created_at: string | null;
    updated_at: string | null;
    account?: Account;
    currency?: Currency;
}
