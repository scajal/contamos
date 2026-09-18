---
paths:
  - 'app/Modules/Billing/Enums/**'
---

# Billing Enums

## Feature enum is the Pennant API
Gate and check plan limits through the Feature enum (`EnsureFeaturesAreActive::using(Feature::…)`, `Feature::enabled($scope)`, `limitOn($plan)`). Enum values must match the Pennant `#[Name]`. Do not pass feature class names or raw strings at call sites.

## Feature enum covers quota and switch features
Every Feature case backs either a quota or a switch feature class. `limitOn(Plan)` applies to quota features and returns null for a switch. `includedIn(Plan)` works for both: the class answer for a switch, and limit null or above zero for a quota. `Plan::features()` lists the limit for quotas and the boolean for switches, and the plan table and upgrade modal render both.
