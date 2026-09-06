---
paths:
  - 'app/Modules/Billing/**'
---

# Billing

## Paddle overlay CSP frame-ancestors is report-only
The console warning Framing https://sandbox-buy.paddle.com/ violates report-only CSP frame-ancestors http://127.0.0.1 comes from Paddle's checkout iframe, not Laravel. artisan serve is http://127.0.0.1:8000; Paddle's report-only policy omits the port so it does not match. It does not block overlay checkout. Do not add frame-ancestors on the app to silence it. Set Paddle Dashboard → Checkout → Checkout settings → Default payment link to the origin you actually browse (for sandbox, https://localhost/ is enough).
