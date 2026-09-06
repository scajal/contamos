---
paths:
  - 'app/Modules/Administration/**'
---

# Administration

## Filament Administration panels
Put Filament panels under Panels/{Panel}/, not app/Filament. Extend the shared Administration Resource and PanelProvider. Register the panel provider in bootstrap/providers.php.

## Soft activation with HasActivation
Models with an on/off flag use Support HasActivation (`is_active`, `activate()` / `deactivate()`, `isActive` scope). Expose activate/deactivate actions. Do not use SoftDeletes or a parallel status enum for that flag.
