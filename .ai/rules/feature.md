---
paths:
  - 'tests/Feature/**'
---

# Feature

## LazilyRefreshDatabase via Pest.php
Rely on `LazilyRefreshDatabase` from `tests/Pest.php` for Feature and Unit. Do not add `RefreshDatabase`, `DatabaseTruncation`, or `DatabaseMigrations` on individual test files.

## Test paths mirror module paths
Place Feature tests under `tests/Feature/Modules/...` and Unit tests under `tests/Unit/Modules/...`, mirroring the `app/Modules/...` path and classname.

## describe() per controller action
In Feature controller tests with multiple actions, wrap cases in `describe('index'|'store'|…)` matching the controller method. Keep Unit tests as a flat `it()` list.

## Use actingMember / memberWithTeam
For team-scoped setup, use `actingMember()` (Feature) or `memberWithTeam()` (Unit). Do not hand-roll User/Team attach, `switchTeam`, and `actingAs` in each test.

## assertInertia with loadDeferredProps
Assert Inertia pages with `assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => …)` after `use Inertia\Testing\AssertableInertia as Assert`. For deferred index props, chain `->loadDeferredProps(...)`.

## assertFlashedToast after modal writes
After a successful write that flashes an Inertia toast, call `assertFlashedToast(trans('Saved'|'Deleted'|…))`. Do not assert toast via raw session keys.

## inertiaModalHeaders for modal visits
When visiting an Inertia modal route, send `withHeaders(inertiaModalHeaders())` so the request is a modal XHR, not a dual base-page visit.

## CarbonImmutable dates
Application dates are CarbonImmutable. Typehint and construct with CarbonImmutable; do not introduce mutable Carbon.
