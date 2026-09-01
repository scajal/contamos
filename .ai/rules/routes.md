---
paths:
  - 'app/Modules/*/Routes/**'
---

# Routes

## Module route registration
Register routes in each module Routes/web.php with [Controller::class, "method"]. Apply auth/feature middleware via Route::middleware()->group(). Reserve closures for trivial one-offs.

## Plain multi-method controllers
Use plain multi-method controller classes with resource-style method names when CRUD-like. Wire them with explicit verb routes. Do not use invokable `__invoke` controllers, `Route::resource` / `apiResource`, or a shared base `Controller`.
