---
paths:
  - 'app/Modules/**'
---

# Modules

## Module-based architecture
Put feature code under app/Modules/{Module}/ with co-located Models, Http, and Routes/web.php. Shared cross-cutting code goes in Support. Register module routes from routes/web.php.
