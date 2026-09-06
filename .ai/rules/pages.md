---
paths:
  - 'resources/js/pages/**/index.tsx'
  - 'resources/js/pages/**/*.tsx'
---

# Pages

## Drive index filters through useFilters
Index pages keep filters in the URL with `useFilters` (`@/hooks/use-filters`): seed `initial` from the `filters` prop, build the URL with the Wayfinder route helper (`routes.index.url({ team }, { query })`), pass `only: [listing, "filters"]`, and `reset: [listing]` for `Inertia::scroll()` listings so pages are replaced instead of appended. Text inputs call `setFilters({...}, { debounced: true })`; selects call it directly. Base UI `Select` uses `value={null}` for the "All" item.

## Deferred listings with Skeleton fallback
Wrap deferred index listings in `<Deferred data="…" fallback={<Skeleton… />}>` and use Empty UI components for the empty state.

## Stacked Item rows need outline for dividers
Item defaults to border-transparent, so `border-b` on stacked rows is invisible. For Item lists inside a bordered container (accounts, tags, currency/tag pickers), use variant="outline" with `rounded-none border-0 border-b last:border-b-0` — the same pattern as accounts/index and tags/index. Do not rely on TableRow borders after migrating a list to Item.
