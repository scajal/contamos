/**
 * JSON shape of App\Modules\Billing\Models\Customer when serialized for Inertia.
 */
import type { Team } from "../../core/identity/models/team";

export interface Customer {
    id: number;
    billable_id: string;
    billable_type: string;
    email: string;
    name: string;
    paddle_id: string;
    trial_ends_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    billable?: Team;
}
