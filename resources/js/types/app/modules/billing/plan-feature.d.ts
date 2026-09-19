import { Feature } from "./enums/feature";

/**
 * JSON shape of a feature row on the upgrade modal, built by
 * App\Modules\Billing\Http\Controllers\Team\SubscriptionController.
 *
 * A quota feature carries its limit per plan, where null means
 * unlimited. A switch feature carries a boolean instead: the plan
 * includes it or it does not.
 */
export interface PlanFeature {
    feature: Feature;
    label: string;
    usage: number | null;
    free: number | boolean | null;
    pro: number | boolean | null;
}
