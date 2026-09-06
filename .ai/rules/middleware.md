---
paths:
    - "app/Modules/Support/Http/Middleware/**"
    - app/Modules/Support/Http/Middleware/HandleInertiaRequests.php
---

# Middleware

## Shared Inertia user and admin flag

Share the authenticated user as top-level `user` with `Inertia::always` (eager-load `currentTeam` when present). Share `auth.canAccessAdministration` from the administration Filament panel. Do not nest the user under `auth` or re-share it from controllers.

## Share user teams with currentTeam

Eager-load teams on the shared Inertia user, and currentTeam when current_team_id is set. Guard with instanceof User so guests do not call loadMissing on null.
