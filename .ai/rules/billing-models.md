---
paths:
  - 'app/Modules/Billing/Models/**'
---

# Billing Models

## Module Cashier models
Extend Laravel\Paddle models in Billing, prefix tables `billing_` with `#[Table]`, and map them with `Cashier::use*Model` in BillingServiceProvider. Do not use the vendor models or Cashier's default table names.
