/**
 * JSON shape of App\Modules\Billing\Models\Transaction when serialized for Inertia.
 */
export interface Transaction {
    id: number;
    billable_type: string;
    billable_id: string;
    paddle_id: string;
    paddle_subscription_id: string | null;
    invoice_number: string | null;
    status: string;
    total: string;
    tax: string;
    currency: string;
    billed_at: string;
    created_at: string | null;
    updated_at: string | null;
}
