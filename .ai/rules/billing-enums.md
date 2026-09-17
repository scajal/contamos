---
paths:
  - 'app/Modules/Billing/Enums/**'
---

# Billing Enums

## Feature enum is the Pennant API
Gate and check plan limits through the Feature enum (`EnsureFeaturesAreActive::using(Feature::…)`, `Feature::enabled($scope)`, `limitOn($plan)`). Enum values must match the Pennant `#[Name]`. Do not pass feature class names or raw strings at call sites.
