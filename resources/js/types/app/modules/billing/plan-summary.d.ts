import type { Feature } from "./enums/feature";
import type { Plan } from "./enums/plan";

/**
 * JSON shape of a single feature limit within a plan summary, built by
 * App\Modules\Marketing\Http\Controllers\HomeController for the plans section.
 */
export interface PlanSummaryFeature {
    key: Feature;
    label: string;
    limit: number | null;
}

/**
 * JSON shape of a plan summary, built by
 * App\Modules\Marketing\Http\Controllers\HomeController for the plans section.
 */
export interface PlanSummary {
    key: Plan;
    label: string;
    features: PlanSummaryFeature[];
}
