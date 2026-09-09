---
paths:
    - resources/js/components/ui/button.tsx
    - resources/js/components/ui/table.tsx
---

# Ui

## Composite buttons need aria-disabled and data-disabled styles

Button with nativeButton={false} (Inertia Link, ModalLink, <a>) cannot use the HTML disabled attribute, so :disabled styles never apply. Base UI sets aria-disabled and data-disabled instead. Keep those variants on Button (opacity-50, pointer-events-none) alongside disabled:. Pagination prev/next is the usual case.

## Tables scroll horizontally on mobile

Do not use overflow-hidden around Table. Prefer auto-sized columns with overflow-x-auto and min-w-0 on wrappers so the scrollport can shrink below the table's min-content width. Exception: day-grouped transaction tables use table-fixed with shared column widths (and a min-w floor) so columns align across separate day tables while still scrolling on narrow viewports.
