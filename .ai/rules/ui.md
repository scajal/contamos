---
paths:
  - resources/js/components/ui/button.tsx
---

# Ui

## Composite buttons need aria-disabled and data-disabled styles
Button with nativeButton={false} (Inertia Link, ModalLink, <a>) cannot use the HTML disabled attribute, so :disabled styles never apply. Base UI sets aria-disabled and data-disabled instead. Keep those variants on Button (opacity-50, pointer-events-none) alongside disabled:. Pagination prev/next is the usual case.
