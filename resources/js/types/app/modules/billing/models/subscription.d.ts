/**
 * JSON shape of App\Modules\Billing\Models\Subscription when serialized for Inertia.
 */
import type { SubscriptionItem } from "./subscription/subscription-item";

export interface Subscription {
    id: number;
    billable_type: string;
    billable_id: string;
    ends_at: string | null;
    paddle_id: string;
    paused_at: string | null;
    status: string;
    trial_ends_at: string | null;
    type: string;
    created_at: string | null;
    updated_at: string | null;
    items?: SubscriptionItem[];
}
