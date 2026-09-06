---
paths:
  - 'app/Modules/Authentication/**'
---

# Authentication

## New WorkOS users are active
WorkOS createUsing() must set is_active to true. Eloquent create() without that column leaves the in-memory model null, which the boolean cast treats as false, so authenticate() redirects new users to logout. Inactive users must be sent to the logout route after WorkOS login.

## Post-login redirect honors url.intended
ResolveAuthenticationRedirect pulls session('url.intended') first (set by Laravel's guest redirect on GET, e.g. an invitation link opened while logged out) and only then falls back to team creation / first-team dashboard. The WorkOS callback regenerates the session ID but keeps its data, so the intended URL survives the round trip. ValidateSessionWithWorkOS invalidates the session on expired tokens, which drops it; that is accepted.
