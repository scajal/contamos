---
paths:
    - "app/Modules/Core/Catalog/Services/**"
---

# Catalog Quote Services

## Catalog Quote services layout

Put quote-sync collaborators under Services/Quote with Contracts (`#[Bind]`), Providers, Actions, and Commands. Constructor-inject providers/contracts; keep Action entry via `using()` after method-injecting the Action into the command.
