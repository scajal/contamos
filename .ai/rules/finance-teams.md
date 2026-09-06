---
paths:
    - "resources/js/pages/finance/teams/**"
---

# Finance Teams

## Parse yyyy-MM-dd grouping keys as local

Group transaction rows with format(occurred_at, yyyy-MM-dd), then format that key with date-fns parse() or parseISO(), not new Date(date). Date-only strings are UTC midnight and show the previous calendar day in timezones west of UTC.
