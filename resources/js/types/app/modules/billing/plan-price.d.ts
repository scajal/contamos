/**
 * JSON shape of a previewed Paddle price, built by
 * App\Modules\Billing\Enums\Plan::price().
 *
 * Every field is null when Paddle could not be reached.
 */
export interface PlanPrice {
    /** Formatted by Paddle, e.g. "$4.99". */
    formatted: string | null;
    /** The same total in minor units, for working out the yearly saving. */
    amount: number | null;
    /** ISO 4217 code of the total, e.g. "USD", for formatting the per-month equivalent. */
    currency: string | null;
}
