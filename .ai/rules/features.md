---
paths:
  - 'app/Modules/Billing/Features/**'
---

# Features

## Class-based Pennant plan limits
Put each plan limit in a Billing Features class with `#[Name('resource:action')]`, `resolve($scope)`, `limitOn(Plan)`, and `usage($scope)`. A null limit means unlimited. Do not define features with `Feature::define` closures or an `app/Features` folder.
