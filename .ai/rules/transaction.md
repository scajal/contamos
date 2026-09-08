---
paths:
    - "app/Modules/Core/Finance/Http/Requests/Team/Transaction/**"
---

# Transaction

## Naive datetime-local is wall-clock time

datetime-local submits YYYY-MM-DDTHH:mm with no offset. App timezone is UTC, so parse that as the user's IANA timezone (request timezone from Intl.DateTimeFormat) and store UTC. On the client, never format a yyyy-MM-dd grouping key with new Date(date): date-only strings are UTC midnight and shift the calendar day west of UTC. Use date-fns parse() / parseISO() instead.

## Blank quote defaults from the rate as of occurred_at

StoreRequest fills an omitted quote from `Currency::quoteAsOf(occurred_at)`. Do not fall back to `currentQuote` (now). If no observation exists on or before that instant, quote stays empty and the required rule fails.
