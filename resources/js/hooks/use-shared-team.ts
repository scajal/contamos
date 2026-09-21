import { usePage } from "@inertiajs/react";

/**
 * Whether the current team is shared with anyone. A ledger of one has no
 * question of who recorded what, so the author of a movement is only worth
 * the room it takes once somebody else can record one too.
 */
export function useSharedTeam(): boolean {
    const { user } = usePage().props;

    return (user?.current_team?.users_count ?? 1) > 1;
}
