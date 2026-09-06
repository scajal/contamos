---
paths:
    - "tests/Unit/**"
---

# Unit

## LazilyRefreshDatabase via Pest.php

Rely on `LazilyRefreshDatabase` from `tests/Pest.php` for Feature and Unit. Do not add `RefreshDatabase`, `DatabaseTruncation`, or `DatabaseMigrations` on individual test files.

## Test paths mirror module paths

Place Feature tests under `tests/Feature/Modules/...` and Unit tests under `tests/Unit/Modules/...`, mirroring the `app/Modules/...` path and classname.

## Use actingMember / memberWithTeam

For team-scoped setup, use `actingMember()` (Feature) or `memberWithTeam()` (Unit). Do not hand-roll User/Team attach, `switchTeam`, and `actingAs` in each test.

## Instantiate Unit collaborators with new

Unit-test Actions, middleware, and support classes by constructing them with `new` against real Eloquent factories. Do not Mockery-mock application classes.

## CarbonImmutable dates

Application dates are CarbonImmutable. Typehint and construct with CarbonImmutable; do not introduce mutable Carbon.
