---
paths:
  - 'tests/Feature/**'
  - tests/Pest.php
  - 'tests/**'
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

## Test files open with an FQDN header docblock

Start every test file with a file-level docblock naming the class under test by leading-backslash FQDN, after the imports: `/** Feature tests for \App\Modules\... */`.

## Fixtures come from factories, never seeders
Create every record, reference currencies included, with model factories and their states; do not call seeders or insert raw rows. Use a fake ISO code (ZZA, ZZB) when the currency code is not what the test is about.

## Double collaborators with fakes, not Mockery
Isolate the outside world with Laravel fakes (Http::fake against the provider's URL constant with a fixture body, Notification::fake) or an anonymous class bound with app()->instance; never mock(), spy() or partialMock(). Wrap each fake in a file-local fakeX() helper.

## it() sentences, keyed datasets, expect()
Write it('<third-person verb> ...'), never test(); chain expect()->and() instead of $this->assert*; give every dataset row a descriptive string key inline in ->with([...]). Keep type coverage at 100%.

## Plans via subscribeToPro, flags via Pennant deactivate
Put a team on Pro with subscribeToPro($team) (cancelPro to drop it) and call Pennant::flushCache() if the team was already resolved; force a refusal with Pennant::for($team)->deactivate(Feature::X) rather than faking the resolver. Alias the facade as Pennant so Feature stays the billing enum.

## Freeze time with travelTo(CarbonImmutable)
Fix the clock with $this->travelTo(CarbonImmutable::parse('YYYY-MM-DD HH:MM:SS')). Do not use Date::setTestNow, freezeTime or string arguments.
