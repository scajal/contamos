---
paths:
  - 'app/Modules/Billing/Features/**'
---

# Features

## Class-based Pennant plan limits
Put each plan limit in a Billing Features class with `#[Name('resource:action')]`, `resolve($scope)`, `limitOn(Plan)`, and `usage($scope)`. A null limit means unlimited. Do not define features with `Feature::define` closures or an `app/Features` folder.

## Two feature shapes: quota and switch
Pennant feature classes come in two shapes, both with `#[Name('resource:action')]` and `resolve($scope)`. A quota feature has `limitOn(Plan): ?int` (null = unlimited) and `usage($scope): int`, and resolves to usage under the limit. A switch feature (pro = true, free = false) has `includedIn(Plan): bool` and no usage, and resolves to whether the scope's plan includes it. Do not model a switch as a quota with a zero limit.
