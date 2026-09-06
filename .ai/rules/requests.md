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

## IndexRequest validates filter values
Index pages use a Form Request named `IndexRequest` (sibling of StoreRequest/UpdateRequest) with `sometimes|nullable` rules on `filter.*` (Rule::enum, boolean, date_format:Y-m-d, Rule::exists scoped to the team). Query Builder only allow-lists filter keys; the IndexRequest is what keeps bad values (unknown enums, unparsable dates) from 500ing.

## IndexRequest owns AllowedFilter list
Put Query Builder `AllowedFilter` definitions on `IndexRequest::filters()`. In the index action call `->allowedFilters(...$request->filters())`; do not inline the allow-list in the controller.

## Validation attributes in lang files
Put custom validation attribute labels in lang/{locale}/validation.php under attributes. Do not add Form Request attributes() or messages() methods.

## distinct belongs on array items
Laravel distinct compares sibling values on wildcard attributes (tag_ids.*), not the parent array. Putting distinct on tag_ids lets duplicate IDs through and the taggables unique index then 500s.
