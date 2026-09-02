---
paths:
    - "app/Modules/**/Models/**"
---

# Models

## ULID primary keys on domain models

Use HasUlids and the DefinesKeyType concern on module domain models.

## Model lifecycle in boot()

Register model lifecycle logic with `boot()` and `static::creating` / `static::updating` closures on the model. Do not add observers for this.

## Derive key names from models

Derive table, primary-key, and foreign-key column names from the model or relation (`getKeyName()`, `getForeignKeyName()`, `getTable()`). Do not hardcode those column names as strings.
