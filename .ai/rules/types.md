---
paths:
  - 'resources/js/types/**'
---

# Types

## Enums as string unions, shapes as interfaces
Mirror a PHP backed enum as export type X = 'a' | 'b' of its values, and a model or DTO as export interface, each in its own .d.ts headed by a 'JSON shape of App\...' docblock.
