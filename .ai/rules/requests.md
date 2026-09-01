---
paths:
  - 'app/Modules/**/Http/Requests/**'
---

# Requests

## Nested Form Request classes
Validate HTTP input with Form Request classes named `StoreRequest` / `UpdateRequest`, nested under the resource path. Do not validate inline with `$request->validate()` or `Validator::make()`.
