---
paths:
    - "app/Modules/Support/Http/Middleware/**"
---

# Middleware

## Shared Inertia user and admin flag

Share the authenticated user as top-level `user` with `Inertia::always` (eager-load `currentTeam` when present). Share `auth.canAccessAdministration` from the administration Filament panel. Do not nest the user under `auth` or re-share it from controllers.
