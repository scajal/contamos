---
paths:
    - "app/Modules/*/Routes/**"
    - "app/Modules/**/Routes/**"
    - routes/console.php
---

# Routes

## Module route registration

Register routes in each module Routes/web.php with [Controller::class, "method"]. Apply auth/feature middleware via Route::middleware()->group(). Reserve closures for trivial one-offs.

## Plain multi-method controllers

Use plain multi-method controller classes with resource-style method names when CRUD-like. Wire them with explicit verb routes. Do not use invokable `__invoke` controllers, `Route::resource` / `apiResource`, or a shared base `Controller`.

## Prefix route names by module

Prefix named routes with the module segment (`identity.`, `finance.`). Keep Authentication guest/auth routes unprefixed (`login`, `authenticate`, `logout`). Administration UI uses Filament route names (`filament.administration.*`), not module HTTP prefixes.

## WorkOS session middleware

Add `ValidateSessionWithWorkOS` alongside `Authenticate` on authenticated module route groups. Do not protect those groups with `Authenticate` alone.

## Nested scopeBindings on child routes

Chain `->scopeBindings()` on nested resource routes so the child must belong to the parent. Do not resolve nested `{parent}/{child}` routes without it.

## Currency admin UI is Filament Administration

Currency and quote admin UI is Filament under Administration (`filament.administration.*`), not Catalog HTTP routes. Catalog remains the domain for Currency models and scheduled quote sync.

## Console routes live in modules

Register schedules in the module `Routes/console.php` and require it from `routes/console.php`. Discover Artisan commands via `bootstrap/app.php` `withCommands` pointing at the module Commands directory. Do not add `app/Console`.
