---
paths:
    - "resources/js/pages/finance/**/_components/fields.tsx"
---

# Components

## Multi-select fields use Combobox chips

Account currencies and transaction tags are multi-select Comboboxes with `multiple`, ComboboxChips, and hidden `name="…[]"` inputs so Laravel receives arrays. Do not go back to checkbox Item lists for these fields.

## Mark Inertia forms dirty from custom controls

Inertia <Form> isDirty only updates on native input/change/reset events. Combobox and ToggleGroup write hidden inputs in React, so after those value changes call markFormDirty() from @/lib/utils. Chip comboboxes also pass useComboboxAnchor to ComboboxChips (ref) and ComboboxContent (anchor) so the dropdown matches the full field width.
