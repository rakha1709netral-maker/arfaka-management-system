---
paths:
  - 'app/Http/Controllers/TourLeader*.php'
  - 'app/Http/Controllers/*Trip*.php'
---

# Controllers

## Keep Tour Leader management role-scoped
Tour Leaders remain User records with UserRole::TourLeader. Management routes use the {tourLeader} binding filtered to that role, and create/update actions must never accept role or activation state from profile forms.

## Iteration 1 assignment and trip access boundaries
Trip assignment is optional and represents the current assignment, not assignment history. Keep one TL per trip for Iteration 1; future multiple TL support should evolve trip_assignments rather than introduce a user_id on trips. Deactivating a TL preserves assignments but blocks session access; only active TLs may be newly assigned. TL detail access outside their assignment returns 404 to avoid disclosing trip existence, while super-admin management remains role-protected with 403. Do not infer settlement or close/reopen workflows from TripStatus values.
