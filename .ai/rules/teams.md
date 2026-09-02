---
paths:
    - "app/Modules/Core/Finance/**"
    - "app/Modules/Core/Identity/**"
---

# Teams

## Team middleware and scoped queries

Protect team routes with `EnsureTeamMembership`, use `scopeBindings()` on nested resource routes, and load or create records via `$team->relation()` or validation rules scoped to the route team.
