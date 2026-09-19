import type { Role } from "./enums/team/role";

/**
 * JSON shape of a role picker option, built by
 * App\Modules\Core\Identity\Enums\Team\Role::options().
 */
export interface RoleOption {
    label: string;
    value: Role;
}
