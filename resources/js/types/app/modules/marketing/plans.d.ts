/**
 * JSON shape of the advertised plans, built by
 * App\Modules\Marketing\Http\Controllers\HomeController.
 *
 * The free plan's limits are read from App\Modules\Billing\Enums\Feature,
 * and the Pro prices are previewed from Paddle — every field is nullable
 * where "no limit" or "price unavailable" is a real state.
 */
export interface Plans {
    free: {
        accounts: number | null;
        members: number | null;
        transactions: number | null;
    };
    pro: {
        /** Formatted by Paddle, e.g. "$4.99". Null when the preview failed. */
        monthly: string | null;
        yearly: string | null;
        /** The same totals in minor units, for working out the yearly saving. */
        monthlyAmount: number | null;
        yearlyAmount: number | null;
    };
}
