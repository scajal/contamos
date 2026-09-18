---
paths:
  - 'app/Modules/**/Http/**'
---

# Http

## Abort guards use Response constants
Guard controller and middleware preconditions with `abort_if(...)` / `abort(...)`, passing `Symfony\Component\HttpFoundation\Response::HTTP_*` constants. Do not pass numeric status codes.
