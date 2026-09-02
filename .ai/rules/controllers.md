---
paths:
    - "app/Modules/**/Http/Controllers/**"
---

# Controllers

## Inertia web responses

Return Inertia::render() for pages and Inertia::modal() for modal forms. Do not use API Resource classes or response()->json() in module web controllers.

## Nested Form Request classes

Validate HTTP input with Form Request classes named `StoreRequest` / `UpdateRequest`, nested under the resource path. Do not validate inline with `$request->validate()` or `Validator::make()`.

## Read validated request data

After Form Request validation, read input with `$request->validated()` or `$request->safe()`. Do not use typed getters (`string()`, `integer()`, …) or `$request->input()` for validated form data.

## Plain multi-method controllers

Use plain multi-method controller classes with resource-style method names when CRUD-like. Wire them with explicit verb routes. Do not use invokable `__invoke` controllers, `Route::resource` / `apiResource`, or a shared base `Controller`.

## Actions with using() for multi-step writes

Put multi-step or transactional domain writes in module Action classes. Method-inject the Action and call `using(...)` with validated arrays. Wrap multiple writes in `DB::transaction`. Keep simple single-model Eloquent create/update in the controller. Do not use `handle`, `execute`, or `__invoke`.

## Flash Toast and return back() after modal writes

After successful store/update from an Inertia modal, flash with `Toast::make(trans('…'))->flash()` and `return back()`. Use `to_route()` only when the user must land on a different named page.

## Defer paginated index listings

For Inertia index pages, pass the listing as `Inertia::defer()` and paginate with `paginate(10)` (length-aware). Do not use `simplePaginate` or `cursorPaginate` for these listings.

## Chain baseRoute on Inertia modals

When returning `Inertia::modal()`, chain `->baseRoute('named.route', …)` so the modal has a named-route background.
