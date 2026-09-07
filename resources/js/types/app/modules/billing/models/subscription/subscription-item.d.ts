/**
 * JSON shape of App\Modules\Billing\Models\Subscription\SubscriptionItem when serialized for Inertia.
 */
import type { Subscription } from "../subscription";

export interface SubscriptionItem {
    id: number;
    price_id: string;
    product_id: string;
    quantity: number;
    status: string;
    subscription_id: number;
    created_at: string | null;
    updated_at: string | null;
    subscription?: Subscription;
}
