---
paths:
    - bootstrap/app.php
---

# Bootstrap

## Console routes live in modules

Register schedules in the module `Routes/console.php` and require it from `routes/console.php`. Discover Artisan commands via `bootstrap/app.php` `withCommands` pointing at the module Commands directory. Do not add `app/Console`.
