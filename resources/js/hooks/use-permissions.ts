import { usePage } from "@inertiajs/react";

import { Permission } from "@/types/app/modules/core/identity/enums/team/permission";

/**
 * Read what the authenticated user may do on their current team,
 * shared by App\Modules\Support\Http\Middleware\HandleInertiaRequests.
 * The server refuses a forbidden action regardless, so this only
 * decides what the interface offers.
 */
export const usePermissions = () => {
    const { permissions } = usePage().props;

    /**
     * Whether the user holds the given permission on
     * the current team.
     */
    const can = (permission: Permission) => (permissions ?? []).includes(permission);

    return { can, permissions: permissions ?? [] };
};
