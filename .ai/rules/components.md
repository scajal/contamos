---
paths:
  - 'resources/js/pages/finance/**/_components/fields.tsx'
---

# Components

## Multi-select fields use Combobox chips
Account currencies and transaction tags are multi-select Comboboxes with `multiple`, ComboboxChips, and hidden `name="…[]"` inputs so Laravel receives arrays. Do not go back to checkbox Item lists for these fields.
