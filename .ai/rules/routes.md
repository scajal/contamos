---
paths:
  - 'app/Modules/*/Routes/**'
---

# Routes

## Module route registration
Register routes in each module Routes/web.php with [Controller::class, "method"]. Apply auth/feature middleware via Route::middleware()->group(). Reserve closures for trivial one-offs.
