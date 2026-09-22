import { PlanValue } from "@/lib/billing";

import { BillingCycle } from "./enums/billing-cycle";
import { Feature } from "./enums/feature";
import { Plan } from "./enums/plan";

/**
 * Where a subscription is in its lifecycle, as reported by
 * App\Modules\Billing\Http\Controllers\Team\SubscriptionController.
 */
export type PlanStatus = "free" | "active" | "trialing" | "grace" | "past_due" | "paused" | "granted";

/**
 * A team-scoped feature with the team's usage and what each plan
 * gives. A quota feature carries a limit, where null means
 * unlimited; a switch feature carries a boolean and no usage.
 */
export interface PlanLimit {
    feature: Feature;
    label: string;
    usage: number | null;
    free: PlanValue;
    pro: PlanValue;
}

/**
 * JSON shape of the plan summary on the billing page.
 */
export interface PlanSummary {
    name: Plan;
    status: PlanStatus;
    cycle: BillingCycle | null;
    ends_at: string | null;
    /** When a plan granted by hand lapses; null means indefinitely. */
    granted_until: string | null;
    trial_ends_at: string | null;
    limits: Array<PlanLimit>;
}

/**
 * The next payment Paddle has scheduled. Null when there is
 * none or Paddle could not be reached.
 */
export interface Renewal {
    /** Formatted by Paddle, e.g. "$4.99". */
    amount: string;
    date: string;
}
