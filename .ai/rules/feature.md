---
paths:
    - "tests/Feature/**"
    - tests/Pest.php
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

## Feature beforeEach isolation fakes

In Feature `pest()->beforeEach`, call `Http::preventStrayRequests()`, `Sleep::fake(syncWithCarbon: true)`, and `Exceptions::fake()`. Keep Feature HTTP outbound, sleeps, and exception reporting faked.

## Refresh factory users before actingAs when the request calls switchTeam

Strict models throw "attribute [current_team_id] either does not exist or was not retrieved" when a request resolves the acting user and calls User::switchTeam() on a User::factory()->create() instance, because the in-memory model lacks that column. Use User::factory()->create([...])->refresh() (or actingMember()) so the user mirrors a DB-loaded auth user.
