---
paths:
  - 'app/Modules/*/Routes/**'
  - 'app/Modules/**/Routes/**'
---

# Routes

## Module route registration

Register routes in each module Routes/web.php with [Controller::class, "method"]. Apply auth/feature middleware via Route::middleware()->group(). Reserve closures for trivial one-offs.

## Plain multi-method controllers

Use plain multi-method controller classes with resource-style method names when CRUD-like. Wire them with explicit verb routes. Do not use invokable `__invoke` controllers, `Route::resource` / `apiResource`, or a shared base `Controller`.

## Prefix route names by module
Prefix named routes with the module segment (`identity.`, `administration.`, `finance.`). Keep Authentication guest/auth routes unprefixed (`login`, `authenticate`, `logout`).

## WorkOS session middleware
Add `ValidateSessionWithWorkOS` alongside `Authenticate` on authenticated module route groups. Do not protect those groups with `Authenticate` alone.

## Nested scopeBindings on child routes
Chain `->scopeBindings()` on nested resource routes so the child must belong to the parent. Do not resolve nested `{parent}/{child}` routes without it.

## Currency HTTP routes use administration prefix
Currency and quote HTTP routes live under Administration (`administration.currencies.*`), not Catalog. Catalog remains the domain for Currency models and scheduled quote sync.
