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
