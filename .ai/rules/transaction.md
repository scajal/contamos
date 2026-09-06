---
paths:
  - 'app/Modules/Core/Finance/Http/Requests/Team/Transaction/**'
---

# Transaction

## Naive datetime-local is wall-clock time
datetime-local submits YYYY-MM-DDTHH:mm with no offset. App timezone is UTC, so parse that as the user's IANA timezone (request timezone from Intl.DateTimeFormat) and store UTC. On the client, never format a yyyy-MM-dd grouping key with new Date(date): date-only strings are UTC midnight and shift the calendar day west of UTC. Use date-fns parse() / parseISO() instead.
