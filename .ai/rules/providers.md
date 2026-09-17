---
paths:
  - 'app/Modules/Billing/Providers/**'
---

# Providers

## Inactive plan limits open upgrade
Discover Billing Features in BillingServiceProvider. Pennant scope is the route team, else the invitation's team, else the authenticated user. `EnsureFeaturesAreActive::whenInactive` redirects to the upgrade modal (invitees get a toast instead). Do not abort 403 on those routes.

## Module Cashier models
Extend Laravel\Paddle models in Billing, prefix tables `billing_` with `#[Table]`, and map them with `Cashier::use*Model` in BillingServiceProvider. Do not use the vendor models or Cashier's default table names.
