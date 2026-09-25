---
paths:
    - "app/Modules/**/Actions/**"
---

# Actions

## Actions with using() for multi-step writes

Put multi-step or transactional domain writes in module Action classes. Method-inject the Action and call `using(...)` with validated arrays. Wrap multiple writes in `DB::transaction`. Keep simple single-model Eloquent create/update in the controller. Do not use `handle`, `execute`, or `__invoke`.

## Fluent for/by then using

Set Action context with fluent `for($model)` and `by($user)` when an owner or actor is needed, then call `using(...)` with validated arrays. Do not pass those context models only as `using()` arguments.

## Name write Actions Create or Update

Name transactional write Actions `Create{Resource}` or `Update{Resource}`. Do not use `Store`, `Save`, or `Handle` prefixes for those writes.

## Guard Action context with Exception
When `using(...)` runs without the `for()` / `by()` context it needs, throw a base `Exception` with a full-sentence message. Do not add custom exception classes for Action guards.

## Share sibling logic via a local Concerns trait
Share logic between sibling Actions with a trait in a `Concerns/` folder next to them, named as a verb phrase (`AppliesTransactionBalances`, `BuildsTransferTransactions`). Keep the trait methods `protected`. Do not extract a base class or a service for this, and do not put module-specific traits in `Support/Concerns`.

## Transactions nest inside TeamActivity::within
Wrap multi-write Action bodies in a DB::transaction() closure inside the Action, never in controllers, and never beginTransaction/commit. For team-scoped writes nest it inside TeamActivity::within() so the change logs as a single activity entry.

## Recipients via notificationRecipients
Send team emails with Notification::send($team->notificationRecipients($type, $causer), $notification). Never pick recipients by role or query memberships inline.
