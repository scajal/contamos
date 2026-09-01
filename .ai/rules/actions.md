---
paths:
  - 'app/Modules/**/Actions/**'
---

# Actions

## Actions with using() for multi-step writes
Put multi-step or transactional domain writes in module Action classes. Method-inject the Action and call `using(...)` with validated arrays. Wrap multiple writes in `DB::transaction`. Keep simple single-model Eloquent create/update in the controller. Do not use `handle`, `execute`, or `__invoke`.
