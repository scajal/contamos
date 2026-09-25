/**
 * JSON shape of App\Modules\Billing\Models\Transaction when serialized for Inertia.
 */
import type { Team } from "../../core/identity/models/team";
import type { Subscription } from "./subscription";

export interface Transaction {
    id: number;
    billed_at: string;
    billable_id: string;
    billable_type: string;
    currency: string;
    invoice_number: string | null;
    paddle_id: string;
    paddle_subscription_id: string | null;
    status: "billed" | "canceled" | "completed" | "draft" | "paid" | "past_due" | "ready";
    tax: string;
    /** Minor units, e.g. "499". */
    total: string;
    /** Formatted by Cashier, e.g. "$4.99". */
    formatted_total: string;
    created_at: string | null;
    updated_at: string | null;
    billable?: Team;
    subscription?: Subscription | null;
}
