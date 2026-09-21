import { Feature } from "@/types/app/modules/billing/enums/feature";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";

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

/**
 * Format a Paddle total in minor units as the equivalent price of one
 * month, in the given locale. The currency decides how many minor units
 * make one major unit, so a two-decimal currency divides by a hundred
 * and a zero-decimal one by nothing. Null when the amount or the
 * currency is missing, which also covers a price cached before the
 * currency was part of it, and a price Paddle could not be reached for.
 */
export function perMonthPrice(price: PlanPrice, months: number, locale: string): string | null {
    if (!price.amount || !price.currency) {
        return null;
    }

    const formatter = new Intl.NumberFormat(locale, { style: "currency", currency: price.currency });

    const decimals = formatter.resolvedOptions().maximumFractionDigits ?? 2;

    return formatter.format(price.amount / 10 ** decimals / months);
}

/**
 * What paying yearly saves against twelve months of the monthly price,
 * as a whole percentage. Null when either price is unknown, and null
 * when it saves nothing, so a caller never has to check for a zero.
 */
export function yearlySaving(prices: { monthly: PlanPrice; yearly: PlanPrice }): number | null {
    if (!prices.monthly.amount || !prices.yearly.amount) {
        return null;
    }

    const saving = Math.round((1 - prices.yearly.amount / (prices.monthly.amount * 12)) * 100);

    return saving > 0 ? saving : null;
}

/**
 * A Paddle total in minor units as a plain decimal string in major
 * units, for places that need the number rather than a formatted
 * price, such as structured data. The currency decides how many minor
 * units make one major unit. Null when the price is unknown.
 */
export function majorUnits(price: PlanPrice): string | null {
    if (!price.amount || !price.currency) {
        return null;
    }

    const decimals =
        new Intl.NumberFormat("en-US", { currency: price.currency, style: "currency" }).resolvedOptions()
            .maximumFractionDigits ?? 2;

    return (price.amount / 10 ** decimals).toFixed(decimals);
}
