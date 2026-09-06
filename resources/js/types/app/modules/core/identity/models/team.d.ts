/**
 * JSON shape of App\Modules\Core\Identity\Models\Team when serialized for Inertia.
 */
import type { Account } from "../../finance/models/team/account";
import type { Tag } from "../../finance/models/team/tag";
import type { Transaction } from "../../finance/models/team/transaction";
import type { Membership } from "./membership";
import type { TeamInvitation } from "./team/team-invitation";
import type { User } from "./user";

export interface Team {
    id: string;
    name: string;
    slug: string;
    created_at: string | null;
    updated_at: string | null;
    accounts?: Account[];
    invitations?: TeamInvitation[];
    membership?: Membership | null;
    plan?: "free" | "pro";
    tags?: Tag[];
    transactions?: Array<Transaction>;
    users?: User[];
}
