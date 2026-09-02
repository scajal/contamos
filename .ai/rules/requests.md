---
paths:
    - "app/Modules/**/Http/Requests/**"
---

# Requests

## Nested Form Request classes

Validate HTTP input with Form Request classes named `StoreRequest` / `UpdateRequest`, nested under the resource path. Do not validate inline with `$request->validate()` or `Validator::make()`.

## Derive key names from models

Derive table, primary-key, and foreign-key column names from the model or relation (`getKeyName()`, `getForeignKeyName()`, `getTable()`). Do not hardcode those column names as strings.

## Method-inject route models into rules

When `rules()` needs a route-bound model, method-inject it with `#[RouteParameter('name')]`. Do not pull it from `$this->route()`.
