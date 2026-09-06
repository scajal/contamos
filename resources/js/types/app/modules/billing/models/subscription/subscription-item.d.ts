/**
 * JSON shape of App\Modules\Billing\Models\Subscription\SubscriptionItem when serialized for Inertia.
 */
export interface SubscriptionItem {
    id: number;
    product_id: string;
    price_id: string;
    quantity: number;
    status: string;
    subscription_id: number;
    created_at: string | null;
    updated_at: string | null;
}
