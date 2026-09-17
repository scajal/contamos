---
paths:
    - "app/Modules/**/Enums/**"
---

# Enums

## Backed string enums in module Enums

Put domain enums under the module `Enums/` tree nested by resource. Use string-backed enums with TitleCase cases and lowercase values. Do not use pure enums or an `app/Enums` folder.

## Enums implement HasLabel
Implement Filament `HasLabel` on every module enum and return a translated label from `getLabel()`. Do not leave domain enums unlabeled.
