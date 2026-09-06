---
paths:
  - 'tests/Feature/Modules/Administration/**'
---

# Modules Administration

## Test Filament currency admin, not HTTP
Currency and quote administration is Filament (filament.administration.resources.currencies.* and QuotesRelationManager), not Inertia HTTP controllers. Test Livewire pages and relation managers. Quote create is only available on EditCurrency, not ViewCurrency (read-only).
