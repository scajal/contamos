/**
 * JSON shape of App\Modules\Core\Finance\Models\Team\BudgetPeriod when serialized for Inertia.
 */
import type { Currency } from "../../../catalog/models/currency";
import type { BudgetPeriodStatus } from "../../enums/budget-period/budget-period-status";
import type { BudgetTargetKind } from "../../enums/budget-period/budget-target-kind";
import type { Category } from "../../enums/transaction/category";
import type { Tag } from "./tag";

export interface BudgetPeriod {
    id: string;
    amount: string;
    category: Category | null;
    currency_id: string;
    from_month: string;
    parent_id: string | null;
    tag_id: string | null;
    team_id: string;
    until_month: string | null;
    warning_threshold: number | null;
    created_at: string | null;
    updated_at: string | null;
    children?: Array<BudgetPeriod>;
    currency?: Currency;
    parent?: BudgetPeriod;
    tag?: Tag;
}

/**
 * How much of a budget a month has used, as the monthly report
 * and the Budgets page both receive it.
 */
export interface BudgetProgress {
    amount: string;
    income: string;
    spent: string;
    remaining: string;
    percentage: number | null;
    /** The percentage the team wants a warning at, if any. */
    warning_threshold: number | null;
}

/**
 * What a budget follows: an area of spending, with the label and
 * the icon the page draws it with, or a tag of the team. The
 * kind is what tells the two apart.
 */
export type BudgetTarget =
    | {
          kind: Extract<BudgetTargetKind, "category">;
          category: { icon: string; label: string; value: Category };
      }
    | {
          kind: Extract<BudgetTargetKind, "tag">;
          tag: Pick<Tag, "id" | "is_active" | "name">;
      };

/**
 * How much of a category budget in force its sub-budgets of
 * the month claim, and what is left for the rest of the
 * category. The nesting is informative, so the rest may
 * read below zero.
 */
export interface BudgetAllocation {
    allocated: string;
    unallocated: string;
}

/**
 * One row of the Budgets page: the stretch itself, what it
 * follows, where it sits relative to the current month, how
 * the month is going against it when it is the one in force,
 * and the sub-budgets nested under it when it is a category
 * budget.
 */
export interface BudgetRow {
    id: string;
    allocation: BudgetAllocation | null;
    amount: string;
    children: Array<BudgetRow>;
    currency: Pick<Currency, "id" | "code" | "decimal_places" | "symbol">;
    from_month: string;
    parent_id: string | null;
    progress: BudgetProgress | null;
    status: BudgetPeriodStatus;
    target: BudgetTarget;
    until_month: string | null;
    warning_threshold: number | null;
}

/**
 * A category budget a tag budget may be nested under, as the
 * budget forms receive it: enough to name it and to match it
 * to the currency picked.
 */
export interface BudgetParentOption {
    id: string;
    amount: string;
    category: { icon: string; label: string; value: Category };
    currency_id: string;
    from_month: string;
    until_month: string | null;
}
