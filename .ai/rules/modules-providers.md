---
paths:
  - 'app/Modules/**/Providers/**'
---

# Modules Providers

## Providers configure, not bind
A module gets one {Module}ServiceProvider only when it must hook the framework (Cashier models, Pennant discovery and scope, the event-to-interface listener, rate limiters, activity-log callbacks). boot() delegates to protected configureX(): void methods, one per concern. Register it in bootstrap/providers.php. No container bindings, $listen arrays or route loading here.
