/**
 * JSON shape of the rows returned by App\Modules\Core\Finance\Models\Team\Account::balancesByCurrency(), and of each account's balances on the accounts list, when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";

export type AccountBalance = Pick<Currency, "id" | "code" | "decimal_places" | "symbol"> & {
    balance: string;
};
