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

## Method-inject collaborators

Method-inject Actions and other collaborators. Use `#[CurrentUser]` when the container should resolve the user. Do not call `app()`, `resolve()`, or `App::make()` in module code.

## Actions with using() for multi-step writes

Put multi-step or transactional domain writes in module Action classes. Method-inject the Action, set context with `for($model)` and `by($user)` when needed, then call `using(...)` with validated arrays. Wrap multiple writes in `DB::transaction`. Keep simple single-model Eloquent create/update in the controller. Do not use `handle`, `execute`, or `__invoke`.

## Flash Toast and return back() after modal writes

After successful store/update from an Inertia modal, flash with `Toast::make(trans('…'))->flash()` and `return back()`. Use `to_route()` only when the user must land on a different named page.

## Defer paginated index listings

For Inertia index pages, pass the listing as `Inertia::defer()` and paginate with `paginate(10)` (length-aware). Do not use `simplePaginate` or `cursorPaginate` for these listings. Use `Inertia::scroll(...)->defer()` only for infinite-scroll index pages.

## Chain baseRoute on Inertia modals

When returning `Inertia::modal()`, chain `->baseRoute('named.route', …)` so the modal has a named-route background.

## Filter index listings with Query Builder

Index pages accept `filter[...]` query params via spatie/laravel-query-builder: `QueryBuilder::for(Model::class | $team->relation(), $request)->allowedFilters(...)` with explicit `AllowedFilter` entries (variadic, not an array). Keep `Inertia::defer()` / `Inertia::scroll()->defer()` and `paginate(10)`, chain `->withQueryString()` so prev/next URLs keep filters, and pass `'filters' => $request->validated('filter', [])` so the page can seed its controls. Use `AllowedFilter::scope()` with a `#[Scope]` model method when the filter needs typed logic (PHPStan rejects `mixed` in `callback()`).

## IndexRequest owns AllowedFilter list

Put Query Builder `AllowedFilter` definitions on `IndexRequest::filters()`. In the index action call `->allowedFilters(...$request->filters())`; do not inline the allow-list in the controller.

## Soft activation with HasActivation

Models with an on/off flag use Support HasActivation (`is_active`, `activate()` / `deactivate()`, `isActive` scope). Expose activate/deactivate actions. Do not use SoftDeletes or a parallel status enum for that flag.

## Feature enum is the Pennant API
Gate and check plan limits through the Feature enum (`EnsureFeaturesAreActive::using(Feature::…)`, `Feature::enabled($scope)`, `limitOn($plan)`). Enum values must match the Pennant `#[Name]`. Do not pass feature class names or raw strings at call sites.

## Check permissions with hasTeamPermission
When a route's permission middleware cannot express the check, guard in the controller with `abort_if` / `abort_unless` on `$user->hasTeamPermission($team, Permission::X)`. Expose the result to Inertia as a boolean prop (`canManageBilling`) rather than sending roles to the frontend.

## Redirect with to_route
Redirect to an application route with `to_route('name', [...])`. Reserve `redirect()` for URLs that are not named routes, such as `redirect()->away($url)` and `redirect()->to($url)`. Do not use `redirect()->route(...)`.

## Pagination per surface
Inertia index pages use ->paginate(10) with page numbers. Page computed collections with forPage(). Never simplePaginate.

## Inject the user with #[CurrentUser]
Receive the signed-in user in controller actions as `#[CurrentUser] User $user` and pass it to Actions via ->by($user). Use $request->user() only in middleware and providers; never auth()->user() or Auth::user().
