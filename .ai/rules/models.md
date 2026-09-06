---
paths:
    - "app/Modules/**/Models/**"
---

# Models

## Fillable is documentation, not a guard

`nunomaduro/essentials` unguards Eloquent models, so every column is mass-assignable. Keep `#[Fillable]` as a reminder of the model's own attributes. Do not list foreign keys, timestamps, or the primary key, and do not treat a missing fillable entry as a mass-assignment restriction.

## ULID primary keys on domain models

Use HasUlids and the DefinesKeyType concern on module domain models.

## Model lifecycle in boot()

Register model lifecycle logic with `boot()` and `static::creating` / `static::updating` closures on the model. Do not add observers for this.

## Derive key names from models

Derive table, primary-key, and foreign-key column names from the model or relation (`getKeyName()`, `getForeignKeyName()`, `getTable()`). Do not hardcode those column names as strings.

## Store enums as string columns
Persist enum-backed attributes as `$table->string(...)` and cast them to the PHP enum in `casts()`. Do not use `$table->enum()`.

## Local scopes via #[Scope]
Define local query scopes as `#[Scope]` protected methods on the model. Do not use legacy `scopeXxx` methods or dedicated Builder classes.

## Soft activation with HasActivation
Models with an on/off flag use Support HasActivation (`is_active`, `activate()` / `deactivate()`, `isActive` scope). Expose activate/deactivate actions. Do not use SoftDeletes or a parallel status enum for that flag.
