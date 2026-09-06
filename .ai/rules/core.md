---
paths:
  - 'app/Modules/Core/**/*.php'
---

# Core

## Team URLs use slug route key
Team getRouteKeyName is slug. Pass getRouteKey() or the Team model into route(), to_route(), and Inertia::modal()->baseRoute(). Do not use getKey() or current_team_id as the {team} URL parameter. current_team_id remains the FK.
