---
paths:
  - 'app/**'
---

# App

## Trip finance amounts, transfers and settlement
Finance is now explicitly in scope beyond Iteration 1. Store IDR as integer rupiah, never float. Approved expenses count once toward costs; advances, reimbursements and returns are TL cash transfers, not additional costs. TL balance = advance + reimbursement - approved TL expenses - returns. Preserve per-user balances even after reassignment/deactivation. All financial mutations lock the trip row; finances_locked_at is authoritative and may only be cleared through reasoned settlement reopening. Entries are immutable except audited approval/rejection/void, receipts stay private, and TLs can only access assigned-trip entries with their own leader_id. Reports filter by trip start date and show lifetime trip totals, not period cash accounting.
