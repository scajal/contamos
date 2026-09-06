---
paths:
  - 'app/Modules/Authentication/**'
---

# Authentication

## New WorkOS users are active
WorkOS createUsing() must set is_active to true. Eloquent create() without that column leaves the in-memory model null, which the boolean cast treats as false, so authenticate() redirects new users to logout. Inactive users must be sent to the logout route after WorkOS login.
