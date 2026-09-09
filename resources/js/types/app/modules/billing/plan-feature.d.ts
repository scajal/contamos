import { Feature } from "./enums/feature";

/**
 * JSON shape of a feature row on the upgrade modal, built by
 * App\Modules\Billing\Http\Controllers\Team\SubscriptionController.
 *
 * A null limit means the plan has no limit for that feature.
 */
export interface PlanFeature {
    feature: Feature;
    label: string;
    free: number | null;
    pro: number | null;
}
