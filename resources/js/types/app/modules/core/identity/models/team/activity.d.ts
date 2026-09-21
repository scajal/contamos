/**
 * JSON shape of an App\Modules\Core\Identity\Models\Team\Activity entry as
 * App\Modules\Core\Identity\Http\Controllers\Team\ActivityController sends it.
 */
import type { ActivitySubject } from "../../enums/team/activity/activity-subject";

/**
 * Where an entry came from: somebody in the team, the platform's
 * support staff, an account that no longer exists, or no one.
 */
export type ActivityCauserKind = "former" | "member" | "support" | "system";

export interface ActivityCauser {
    kind: ActivityCauserKind;
    name: string | null;
}

export interface ActivityChange {
    field: string;
    old: string | string[] | boolean | null;
    new: string | string[] | boolean | null;
    old_label: string | string[] | null;
    new_label: string | string[] | null;
}

/**
 * A timeline row. The values it changed only travel with the
 * detail dialog, which sends `ActivityEntry & { changes }`.
 */
export interface ActivityEntry {
    id: number;
    batch_uuid: string | null;
    causer: ActivityCauser;
    created_at: string | null;
    event: string | null;
    subject_id: string | null;
    subject_label: string | null;
    subject_type: ActivitySubject | null;
}
