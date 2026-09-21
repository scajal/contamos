import type { ActivitySubject } from "./enums/team/activity/activity-subject";

/**
 * JSON shape of a record type filter option, built by
 * App\Modules\Core\Identity\Enums\Team\Activity\ActivitySubject::options().
 */
export interface ActivitySubjectOption {
    label: string;
    value: ActivitySubject;
}
