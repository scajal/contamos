/**
 * JSON shape of a previewed Paddle price, built by
 * App\Modules\Billing\Enums\Plan::price().
 *
 * Both fields are null when Paddle could not be reached.
 */
export interface PlanPrice {
    /** Formatted by Paddle, e.g. "$4.99". */
    formatted: string | null;
    /** The same total in minor units, for working out the yearly saving. */
    amount: number | null;
}
