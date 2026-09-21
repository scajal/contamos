/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\BudgetPeriod when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";
import type { BudgetPeriodStatus } from "../../enums/budget-period/budget-period-status";
import type { Tag } from "./tag";

export interface BudgetPeriod {
    id: string;
    amount: string;
    currency_id: string;
    from_month: string;
    tag_id: string;
    until_month: string | null;
    created_at: string | null;
    updated_at: string | null;
    currency?: Currency;
    tag?: Tag;
}

/**
 * How much of a tag's budget a month has used, as the monthly
 * report and the Budgets page both receive it.
 */
export interface BudgetProgress {
    amount: string;
    income: string;
    spent: string;
    remaining: string;
    percentage: number | null;
}

/**
 * One row of the Budgets page: the stretch itself, where it sits
 * relative to the current month, and how the month is going
 * against it when it is the one in force.
 */
export interface BudgetRow {
    id: string;
    amount: string;
    currency: Pick<Currency, "id" | "code" | "decimal_places" | "symbol">;
    from_month: string;
    progress: BudgetProgress | null;
    status: BudgetPeriodStatus;
    tag: Pick<Tag, "id" | "is_active" | "name">;
    until_month: string | null;
}
