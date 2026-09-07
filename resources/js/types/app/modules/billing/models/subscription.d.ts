/**
 * JSON shape of App\Modules\Billing\Models\Subscription when serialized for Inertia.
 */
import type { Team } from "../../core/identity/models/team";
import type { SubscriptionItem } from "./subscription/subscription-item";
import type { Transaction } from "./transaction";

export interface Subscription {
    id: number;
    billable_id: string;
    billable_type: string;
    ends_at: string | null;
    paddle_id: string;
    paused_at: string | null;
    status: "active" | "canceled" | "past_due" | "paused" | "trialing";
    trial_ends_at: string | null;
    type: string;
    created_at: string | null;
    updated_at: string | null;
    billable?: Team;
    items?: SubscriptionItem[];
    transactions?: Transaction[];
}
