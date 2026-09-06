---
paths:
  - 'app/Modules/Billing/**'
---

# Billing

## Paddle overlay CSP frame-ancestors is report-only
The console warning Framing https://sandbox-buy.paddle.com/ violates report-only CSP frame-ancestors http://127.0.0.1 comes from Paddle's checkout iframe, not Laravel. artisan serve is http://127.0.0.1:8000; Paddle's report-only policy omits the port so it does not match. It does not block overlay checkout. Do not add frame-ancestors on the app to silence it. Set Paddle Dashboard → Checkout → Checkout settings → Default payment link to the origin you actually browse (for sandbox, https://localhost/ is enough).

## Paddle checkout 400 is missing default payment link
POST sandbox-checkout-service.paddle.com/transaction-checkout 400 is transaction_default_checkout_url_not_set. Paddle requires a Default payment link before any checkout or transaction create. There is no API for this. Set it at https://sandbox-vendors.paddle.com/checkout-settings (sandbox) to APP_URL, e.g. http://127.0.0.1:8000 or https://localhost/. Price IDs and customer IDs being valid does not bypass this.
