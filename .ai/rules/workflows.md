---
paths:
    - ".github/workflows/**"
---

# Workflows

## CI bootstrap is composer setup

tests.yml runs `composer setup` then `composer ci:check`. Keep a `setup` script in composer.json (install, .env, key, sqlite file, migrate, pnpm install --frozen-lockfile, pnpm build). Do not remove it; CI has no other bootstrap. Use pnpm, not npm — ci:check and the lockfile are pnpm.
