---
paths:
  - resources/js/components/ui/button.tsx
  - resources/js/components/ui/table.tsx
---

# Ui

## Composite buttons need aria-disabled and data-disabled styles
Button with nativeButton={false} (Inertia Link, ModalLink, <a>) cannot use the HTML disabled attribute, so :disabled styles never apply. Base UI sets aria-disabled and data-disabled instead. Keep those variants on Button (opacity-50, pointer-events-none) alongside disabled:. Pagination prev/next is the usual case.

## Tables scroll horizontally on mobile
Do not use table-fixed or overflow-hidden around Table. The Table container is overflow-x-auto with min-w-0; the table itself is min-w-max so columns size to content and the wrapper scrolls instead of clipping or overlapping cells. Keep min-w-0 on flex ancestors (layout content, table wrappers) so the scrollport can shrink below the table's min-content width.
