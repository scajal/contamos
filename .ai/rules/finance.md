---
paths:
    - "resources/js/pages/finance/**"
---

# Finance

## Color income amounts with success

Income (positive) amounts use text-success (green). Expenses use text-destructive. Do not leave income in the default foreground or brand lime (text-primary).

## Create transaction is amount-first

The create sheet leads with type and amount. Date, notes, tags, and rate sit behind chips that open popovers. Values stay in React state and hidden inputs when a popover closes. A success-color check icon on the right of the chip marks a non-default value (date not today, a note, tags, or an overridden rate). Show the rate chip whenever a currency is selected. Do not keep Quote/Cotización on the happy path.

## Existing rates stay overridable

When a catalog rate exists, the create sheet must still let the user override it. The Rate chip ("Tipo de cambio") opens a popover prefilled with quoteAsOf. Do not show a "Rate of :date" / USD-equivalent line under the amount. Hide the rate field until they open the chip, except when quoteAsOf is missing — then open the popover so they can fill it.

## Type toggle does not mark the create form dirty

The create-transaction type ToggleGroup must not call markFormDirty. Type is already chosen when the sheet opens (Income/Expense entry), and the hidden type input still submits the current value. Marking dirty would enable Register with an empty amount.

## Type toggle cannot be cleared

The create-transaction type ToggleGroup is exclusive but still fires onValueChange([]) when the pressed item is clicked again. Ignore that empty value so type stays income or expense; otherwise t(type) crashes in the sheet title.
