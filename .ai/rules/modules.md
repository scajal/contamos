---
paths:
    - "app/Modules/**"
---

# Modules

## Module-based architecture

Put feature code under app/Modules/{Module}/ with co-located Models, Http, and Routes/web.php. Shared cross-cutting code goes in Support. Register module routes from routes/web.php.

## Nest code under parent resource

Place parent-owned Models, Controllers, Actions, and Requests under a parent segment directory that matches ownership and routing (for example `Team/` or `User/`). Keep unowned resources at the module root.

## Eloquent without extra layers

Query and persist with Eloquent in controllers and Actions. Do not add Services, Repositories, Queries, Data/DTO classes, or domain Events/Listeners.

## Method-inject collaborators

Method-inject Actions and other collaborators. Use `#[CurrentUser]` and `#[RouteParameter]` when the container should resolve the user or a route model. Do not call `app()`, `resolve()`, or `App::make()` in module code.

## Derive key names from models

Derive table, primary-key, and foreign-key column names from the model or relation (`getKeyName()`, `getForeignKeyName()`, `getTable()`). Do not hardcode those column names as strings.

## Declare strict_types

Declare `strict_types=1` in every PHP file. Do not omit it.
