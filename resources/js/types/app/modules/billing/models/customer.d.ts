/**
 * JSON shape of App\Modules\Billing\Models\Customer when serialized for Inertia.
 */
export interface Customer {
    id: number;
    billable_type: string;
    billable_id: string;
    email: string;
    name: string;
    paddle_id: string;
    trial_ends_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}
