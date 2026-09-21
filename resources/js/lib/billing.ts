import { Feature } from "@/types/app/modules/billing/enums/feature";

/**
 * What a plan gives for a feature: a quota feature reports its
 * limit, where null means unlimited, and a switch feature reports
 * whether the plan includes it.
 */
export type PlanValue = number | boolean | null;

/**
 * Whether the row describes a switch feature, which the plan
 * either includes or does not, rather than a quota with a limit.
 */
export function isSwitchValue(value: PlanValue): value is boolean {
    return typeof value === "boolean";
}

/**
 * Whether the team is over the quota the plan allows, which a
 * free team can be after an earlier Pro period.
 */
export function isOverLimit(row: { free: PlanValue; usage?: number | null }): boolean {
    return typeof row.free === "number" && typeof row.usage === "number" && row.usage > row.free;
}

/**
 * Features whose usage above the free limit means older data is
 * hidden, not that the team is blocked from adding more. The page
 * that hides the data explains it, so the billing page's limits
 * warning leaves them out.
 */
const HIDDEN_ABOVE_LIMIT: Array<Feature> = ["history:view"];

/**
 * Whether the team is over a quota it is blocked from adding to,
 * which is what the billing page warns about.
 */
export function isOverAddableLimit(row: { feature: Feature; free: PlanValue; usage?: number | null }): boolean {
    return !HIDDEN_ABOVE_LIMIT.includes(row.feature) && isOverLimit(row);
}
