---
paths:
  - 'app/Modules/Administration/**'
---

# Administration

## Filament Administration panels
Put Filament panels under Panels/{Panel}/, not app/Filament. Extend the shared Administration Resource and PanelProvider. Register the panel provider in bootstrap/providers.php.
