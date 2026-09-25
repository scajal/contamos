---
paths:
  - 'app/Modules/**/Helpers/**'
---

# Helpers

## Helpers are static classes
A helper is a noun-named class with static methods (state in protected static props), autoloaded by PSR-4. Never add global functions or a composer files entry. Module-specific helpers stay in that module's Helpers; cross-cutting ones live in Support/Helpers.
