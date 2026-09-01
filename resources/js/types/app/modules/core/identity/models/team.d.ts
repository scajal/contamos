/**
 * JSON shape of App\Modules\Core\Identity\Models\Team when serialized for Inertia.
 */
import type { Account } from "../../finance/models/team/account";
import type { Transaction } from "../../finance/models/team/transaction";
import type { Membership } from "./membership";
import type { User } from "./user";

export interface Team {
    id: string;
    name: string;
    slug: string;
    created_at: string | null;
    updated_at: string | null;
    accounts?: Account[];
    membership?: Membership | null;
    transactions?: Array<Transaction>;
    users?: User[];
}
