---
paths:
  - 'app/Modules/**/Models/**'
---

# Models

## ULID primary keys on domain models
Use HasUlids and the DefinesKeyType concern on module domain models.

## Model lifecycle in boot()
Register model lifecycle logic with `boot()` and `static::creating` / `static::updating` closures on the model. Do not add observers for this.
