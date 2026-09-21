---
paths:
    - "resources/js/pages/**/index.tsx"
    - "resources/js/pages/**/*.tsx"
---

# Pages

## Drive index filters through useFilters

Index pages keep filters in the URL with `useFilters` (`@/hooks/use-filters`): seed `initial` from the `filters` prop, build the URL with the Wayfinder route helper (`routes.index.url({ team }, { query })`), pass `only: [listing, "filters"]`, and `reset: [listing]` for `Inertia::scroll()` listings so pages are replaced instead of appended. Text inputs call `setFilters({...}, { debounced: true })`; selects call it directly. Base UI `Select` uses `value={null}` for the "All" item.

## Deferred listings with Skeleton fallback

Wrap deferred index listings in `<Deferred data="…" fallback={<Skeleton… />}>` and use Empty UI components for the empty state.

## Stacked Item rows need outline for dividers

Item defaults to border-transparent, so `border-b` on stacked rows is invisible. For Item lists inside a bordered container (accounts, tags, currency/tag pickers), use variant="outline" with `rounded-none border-0 border-b last:border-b-0` — the same pattern as accounts/index and tags/index. Do not rely on TableRow borders after migrating a list to Item.

## Sheet body padding matches header and footer

Inertia modal Sheets use SheetHeader/SheetFooter (p-6). The scrollable body between them is px-6, not px-4, so fields line up with the title and actions. Do not change the mobile sidebar Sheet (p-0).

## Open modal routes with ModalLink
Open Inertia modal routes with `ModalLink` and a Wayfinder `.url()`. Use `Link` only for full-page visits. Do not point a normal `Link` at a modal route.

## Page props type is PageProps
Inertia page components declare their props as a local `type PageProps = { … }` and destructure it in the component signature. Reserve `type Props` for colocated `_components/` files.
