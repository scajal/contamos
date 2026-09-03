---
paths:
    - "app/Modules/**/Actions/**"
---

# Actions

## Actions with using() for multi-step writes

Put multi-step or transactional domain writes in module Action classes. Method-inject the Action and call `using(...)` with validated arrays. Wrap multiple writes in `DB::transaction`. Keep simple single-model Eloquent create/update in the controller. Do not use `handle`, `execute`, or `__invoke`.

## Fluent for/by then using

Set Action context with fluent `for($model)` and `by($user)` when an owner or actor is needed, then call `using(...)` with validated arrays. Do not pass those context models only as `using()` arguments.

## Name write Actions Create or Update
Name transactional write Actions `Create{Resource}` or `Update{Resource}`. Do not use `Store`, `Save`, or `Handle` prefixes for those writes.
