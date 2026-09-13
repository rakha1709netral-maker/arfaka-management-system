---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Terminate inactive authenticated sessions
Protected web routes run auth before the active middleware. When an authenticated account is inactive, log it out, invalidate the session, regenerate the CSRF token, and return 403 so an existing session cannot continue.
